<?php

namespace App\Services;

use App\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\PaymentStatus;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MidtransSnap
{
    /** Lama transaksi Snap berlaku sebelum klien perlu memulai pembayaran baru. */
    public const EXPIRY_HOURS = 24;

    public function __construct(private AuditLogger $audit, private StaffNotifier $notifier) {}

    /**
     * Mulai atau lanjutkan pembayaran tagihan lewat halaman Snap (FR-76). Transaksi yang
     * masih berlaku dipakai ulang; bila sudah kedaluwarsa atau gagal, dibuat Order ID baru.
     *
     * @throws RequestException bila Midtrans menolak permintaan
     */
    public function start(Invoice $invoice, string $finishUrl): InvoicePayment
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw ValidationException::withMessages(['invoice' => 'Tagihan ini sudah lunas.']);
        }

        $open = $invoice->payments()
            ->whereIn('status', [PaymentStatus::Created, PaymentStatus::Pending])
            ->where('expires_at', '>', now())
            ->whereNotNull('redirect_url')
            ->latest('id')->first();

        if ($open !== null) {
            return $open;
        }

        $invoice->loadMissing('ticket.client');
        $client = $invoice->ticket->client;
        $orderId = str_replace('/', '-', $invoice->number).'-'.now()->format('YmdHis');

        $response = Http::withBasicAuth((string) config('services.midtrans.server_key'), '')
            ->acceptJson()->timeout(30)
            ->post($this->baseUrl().'/snap/v1/transactions', [
                'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $invoice->amount],
                'item_details' => [[
                    'id' => $invoice->number,
                    'price' => $invoice->amount,
                    'quantity' => 1,
                    'name' => mb_substr("{$invoice->label()} {$invoice->ticket->code}", 0, 50),
                ]],
                'customer_details' => array_filter([
                    'first_name' => mb_substr((string) ($client->pic_name ?: $client->name), 0, 50),
                    'email' => $client->email,
                    'phone' => $client->whatsapp,
                ]),
                'callbacks' => ['finish' => $finishUrl],
                'expiry' => ['unit' => 'hour', 'duration' => self::EXPIRY_HOURS],
            ])
            ->throw();

        return DB::transaction(function () use ($invoice, $orderId, $response): InvoicePayment {
            $payment = $invoice->payments()->create([
                'order_id' => $orderId,
                'gross_amount' => $invoice->amount,
                'status' => PaymentStatus::Created,
                'snap_token' => $response->json('token'),
                'redirect_url' => $response->json('redirect_url'),
                'expires_at' => now()->addHours(self::EXPIRY_HOURS),
            ]);
            $invoice->update(['status' => InvoiceStatus::AwaitingPayment]);

            $this->audit->record('invoice.payment_started', $invoice, after: ['order_id' => $orderId, 'amount' => $invoice->amount],
                source: 'client', ticketCode: $invoice->ticket->code);

            return $payment;
        });
    }

    /**
     * Terapkan notifikasi HTTP Midtrans setelah tanda tangan SHA-512-nya cocok dengan server
     * key. Pembayaran berhasil melunasi tagihan secara otomatis (FR-77); notifikasi berulang
     * tidak mengubah tagihan yang sudah lunas.
     *
     * @param  array<string, mixed>  $payload
     * @return 'ok'|'invalid_signature'|'unknown_order'|'amount_mismatch'
     */
    public function handleNotification(array $payload): string
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $expected = hash('sha512', $orderId.($payload['status_code'] ?? '').$grossAmount.config('services.midtrans.server_key'));

        if (! hash_equals($expected, (string) ($payload['signature_key'] ?? ''))) {
            return 'invalid_signature';
        }

        return DB::transaction(function () use ($payload, $orderId, $grossAmount): string {
            $payment = InvoicePayment::query()->where('order_id', $orderId)->lockForUpdate()->first();

            if ($payment === null) {
                return 'unknown_order';
            }

            if ((int) round((float) $grossAmount) !== $payment->gross_amount) {
                return 'amount_mismatch';
            }

            $status = PaymentStatus::fromMidtrans((string) ($payload['transaction_status'] ?? ''), $payload['fraud_status'] ?? null);
            $paidAt = $status === PaymentStatus::Paid
                ? (filled($payload['settlement_time'] ?? null) ? Carbon::parse($payload['settlement_time'], 'Asia/Jakarta') : now())
                : null;

            $payment->update([
                'status' => $payment->status === PaymentStatus::Paid ? PaymentStatus::Paid : $status,
                'transaction_status' => $payload['transaction_status'] ?? null,
                'payment_type' => $payload['payment_type'] ?? null,
                'transaction_id' => $payload['transaction_id'] ?? null,
                'paid_at' => $payment->paid_at ?? $paidAt,
                'last_notification' => $payload,
            ]);

            $invoice = Invoice::query()->whereKey($payment->invoice_id)->lockForUpdate()->firstOrFail();
            $this->applyToInvoice($invoice, $payment, $status);

            return 'ok';
        });
    }

    private function applyToInvoice(Invoice $invoice, InvoicePayment $payment, PaymentStatus $status): void
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            return;
        }

        if ($status === PaymentStatus::Paid) {
            $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => $payment->paid_at, 'payment_type' => $payment->payment_type]);
            $this->audit->record('invoice.paid', $invoice, after: [
                'order_id' => $payment->order_id,
                'amount' => $payment->gross_amount,
                'payment_type' => $payment->payment_type,
            ], source: 'system', ticketCode: $invoice->ticket->code);
            $this->notifier->invoicePaid($invoice);

            return;
        }

        $stillOpen = $invoice->payments()->whereIn('status', [PaymentStatus::Created, PaymentStatus::Pending])->where('expires_at', '>', now())->exists();
        $invoice->update(['status' => $status === PaymentStatus::Pending || $stillOpen ? InvoiceStatus::AwaitingPayment : InvoiceStatus::Issued]);
    }

    private function baseUrl(): string
    {
        return config('services.midtrans.is_production') ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }
}
