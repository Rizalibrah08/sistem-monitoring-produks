<?php

namespace Tests\Feature;

use App\Actions\IssueInvoice;
use App\ContractStatus;
use App\InvoiceStatus;
use App\InvoiceTerm;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\UploadToken;
use App\Models\User;
use App\PaymentStatus;
use App\TicketStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-uji';

    private User $marketing;

    private Ticket $ticket;

    private Contract $contract;

    private bool $midtransDown = false;

    private int $snapCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['services.midtrans.server_key' => self::SERVER_KEY, 'services.midtrans.is_production' => false]);
        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => function () {
            if ($this->midtransDown) {
                return Http::response(['error_messages' => ['Access denied']], 401);
            }

            $token = 'snap-'.++$this->snapCount;

            return Http::response(['token' => $token, 'redirect_url' => "https://app.sandbox.midtrans.com/snap/v4/redirection/{$token}"], 201);
        }]);
        $this->travelTo('2026-11-05 10:00:00');
        $this->marketing = $this->createStaff('marketing');
        $this->ticket = Ticket::factory()->create(['client_id' => Client::factory()->create(['marketing_user_id' => $this->marketing->id, 'email' => 'nadia@herbal.test', 'pic_name' => 'Nadia'])->id]);
        $this->contract = Contract::factory()->create([
            'ticket_id' => $this->ticket->id, 'number' => 'KTR/2026/0001', 'service_value' => 30_000_000, 'dp_percent' => 40,
            'payment_due_days' => 7, 'status' => ContractStatus::Signed, 'signed_path' => 'contracts/ttd.pdf', 'created_by' => $this->marketing->id,
        ]);
    }

    public function test_confirming_the_contract_issues_the_down_payment_invoice_once(): void
    {
        $this->actingAs($this->marketing)->post("/contracts/{$this->contract->id}/confirm")->assertSessionHasNoErrors();

        $invoice = Invoice::query()->sole();
        $this->assertSame('INV/2026/0001', $invoice->number);
        $this->assertSame(InvoiceTerm::DownPayment, $invoice->term);
        $this->assertSame(40, $invoice->percent);
        $this->assertSame(12_000_000, $invoice->amount);
        $this->assertSame('2026-11-12', $invoice->due_date->toDateString());
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('INV/2026/0001', Activity::query()->where('event', 'contract.confirmed')->sole()->attribute_changes['attributes']['dp_invoice']);
        $this->assertDatabaseHas('activity_log', ['event' => 'invoice.issued', 'ticket_code' => $this->ticket->code, 'source' => 'system']);

        $this->assertSame($invoice->id, app(IssueInvoice::class)->handle($this->contract->refresh(), InvoiceTerm::DownPayment)->id);
        $settlement = app(IssueInvoice::class)->handle($this->contract, InvoiceTerm::Settlement);
        $this->assertSame(['INV/2026/0002', 60, 18_000_000], [$settlement->number, $settlement->percent, $settlement->amount]);
    }

    public function test_the_client_is_sent_to_a_midtrans_snap_page_that_is_reused_while_still_valid(): void
    {
        $invoice = $this->invoice();
        $plain = $this->link();

        $this->post("/unggah/{$plain}/tagihan/{$invoice->id}/bayar")->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirection/snap-1');
        $this->post("/unggah/{$plain}/tagihan/{$invoice->id}/bayar")->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirection/snap-1');

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://app.sandbox.midtrans.com/snap/v1/transactions'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode(self::SERVER_KEY.':'))
            && $request['transaction_details']['gross_amount'] === 12_000_000
            && $request['transaction_details']['order_id'] === 'INV-2026-0001-20261105100000'
            && $request['customer_details']['email'] === 'nadia@herbal.test'
            && $request['callbacks']['finish'] === route('client-uploads.show', $plain));
        $payment = $invoice->payments()->sole();
        $this->assertSame(PaymentStatus::Created, $payment->status);
        $this->assertSame(InvoiceStatus::AwaitingPayment, $invoice->refresh()->status);

        $this->travel(25)->hours();
        $this->post("/unggah/{$plain}/tagihan/{$invoice->id}/bayar")->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirection/snap-2');
        $this->assertSame(2, $invoice->payments()->count());
    }

    public function test_a_link_only_pays_invoices_of_its_own_ticket_and_reports_midtrans_errors(): void
    {
        $plain = $this->link();
        $foreign = Invoice::factory()->create();
        $this->post("/unggah/{$plain}/tagihan/{$foreign->id}/bayar")->assertNotFound();

        $paid = $this->invoice(['status' => InvoiceStatus::Paid]);
        $this->post("/unggah/{$plain}/tagihan/{$paid->id}/bayar")->assertSessionHas('status', 'Tagihan ini sudah lunas. Terima kasih.');

        $this->midtransDown = true;
        $other = $this->invoice(['term' => InvoiceTerm::Settlement]);
        $this->from("/unggah/{$plain}")->post("/unggah/{$plain}/tagihan/{$other->id}/bayar")
            ->assertRedirect("/unggah/{$plain}")
            ->assertSessionHasErrors(['invoice' => 'Pembayaran belum dapat dimulai. Coba lagi beberapa saat atau hubungi PIC Marketing Anda.']);
        $this->assertSame(InvoiceStatus::Issued, $other->refresh()->status);
    }

    public function test_a_verified_settlement_notification_marks_the_invoice_paid_once(): void
    {
        $invoice = $this->invoice();
        $this->post("/unggah/{$this->link()}/tagihan/{$invoice->id}/bayar");
        $orderId = $invoice->payments()->value('order_id');

        $this->postJson('/midtrans/notification', $this->notification($orderId, 'settlement', ['payment_type' => 'bank_transfer', 'settlement_time' => '2026-11-05 11:30:00']))
            ->assertOk()->assertJson(['status' => 'ok']);
        $this->postJson('/midtrans/notification', $this->notification($orderId, 'settlement', ['payment_type' => 'bank_transfer']))->assertOk();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('bank_transfer', $invoice->payment_type);
        $this->assertSame('2026-11-05 11:30:00', $invoice->paid_at->format('Y-m-d H:i:s'));
        $this->assertSame(PaymentStatus::Paid, $invoice->payments()->sole()->status);
        $this->assertSame(1, Activity::query()->where('event', 'invoice.paid')->count());
    }

    public function test_notifications_with_a_bad_signature_unknown_order_or_wrong_amount_are_rejected(): void
    {
        $invoice = $this->invoice();
        $this->post("/unggah/{$this->link()}/tagihan/{$invoice->id}/bayar");
        $orderId = $invoice->payments()->value('order_id');

        $this->postJson('/midtrans/notification', [...$this->notification($orderId, 'settlement'), 'signature_key' => str_repeat('0', 128)])->assertForbidden();
        $this->postJson('/midtrans/notification', $this->notification('INV-LAIN-1', 'settlement'))->assertNotFound();
        $this->postJson('/midtrans/notification', $this->notification($orderId, 'settlement', [], '1000.00'))->assertStatus(422);

        $this->assertSame(InvoiceStatus::AwaitingPayment, $invoice->refresh()->status);
    }

    public function test_expired_or_pending_transactions_update_the_invoice_without_paying_it(): void
    {
        $invoice = $this->invoice();
        $this->post("/unggah/{$this->link()}/tagihan/{$invoice->id}/bayar");
        $orderId = $invoice->payments()->value('order_id');

        $this->postJson('/midtrans/notification', $this->notification($orderId, 'pending', ['payment_type' => 'qris']))->assertOk();
        $this->assertSame(InvoiceStatus::AwaitingPayment, $invoice->refresh()->status);

        $this->postJson('/midtrans/notification', $this->notification($orderId, 'expire', ['payment_type' => 'qris']))->assertOk();
        $this->assertSame(InvoiceStatus::Issued, $invoice->refresh()->status);
        $this->assertSame(PaymentStatus::Failed, $invoice->payments()->sole()->status);
    }

    public function test_finance_sees_all_invoices_with_outstanding_totals_and_overdue_filter(): void
    {
        $overdue = $this->invoice(['due_date' => '2026-11-01']);
        Invoice::factory()->paid()->create(['amount' => 5_000_000]);
        $closed = Invoice::factory()->create(['amount' => 7_000_000]);
        $closed->ticket->update(['status' => TicketStatus::Completed]);

        $finance = $this->createStaff('finance', ['email' => 'finance@aisha.test']);
        $this->actingAs($finance)->withoutVite()->get('/invoices')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Backend/Invoices', false)
                ->has('invoices.data', 3)
                ->where('summary.outstanding', 19_000_000)
                ->where('summary.unpaid_count', 2)
                ->where('summary.overdue_count', 1)
                ->where('invoices.data.0.number', $overdue->number)
                ->where('invoices.data.0.is_overdue', true)
                ->where('navigation', fn ($items): bool => collect($items)->contains(fn (array $item): bool => $item['id'] === 'invoices' && $item['available'] === true)));

        $this->get('/invoices?status=lewat_jatuh_tempo')->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1));
        $this->actingAs($this->marketing)->get('/invoices')->assertForbidden();
        $this->get("/tickets/{$this->ticket->id}")->assertInertia(fn (Assert $page) => $page->where('invoices.0.number', $overdue->number)->where('invoices.0.is_overdue', true));
    }

    /** @param array<string, mixed> $attributes */
    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::factory()->create([
            'contract_id' => $this->contract->id, 'number' => 'INV/2026/000'.(Invoice::query()->count() + 1), 'amount' => 12_000_000, 'percent' => 40, ...$attributes,
        ]);
    }

    private function link(): string
    {
        UploadToken::factory()->withToken($plain = Str::random(UploadToken::TOKEN_LENGTH))->create(['ticket_id' => $this->ticket->id, 'created_by' => $this->marketing->id]);

        return $plain;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function notification(string $orderId, string $status, array $extra = [], string $grossAmount = '12000000.00'): array
    {
        $statusCode = match ($status) {
            'settlement', 'capture' => '200',
            'pending' => '201',
            default => '407',
        };

        return [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => $status,
            'transaction_id' => 'trx-'.$orderId,
            'signature_key' => hash('sha512', $orderId.$statusCode.$grossAmount.self::SERVER_KEY),
            ...$extra,
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function createStaff(string $role, array $attributes = []): User
    {
        $this->seed();
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
