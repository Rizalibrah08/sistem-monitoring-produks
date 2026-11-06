<?php

namespace App\Http\Controllers;

use App\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivableController extends Controller
{
    /**
     * Laporan tagihan, pelunasan, dan piutang per klien (FR-80), dengan filter tanggal
     * terbit tagihan dan nama klien.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $invoices = $this->invoices($filters);
        $today = now()->startOfDay();

        $clients = $invoices->groupBy('ticket.client_id')->map(function (Collection $items) use ($today): array {
            $unpaid = $items->where('status', '!=', InvoiceStatus::Paid);

            return [
                'client_id' => $items->first()->ticket->client_id,
                'client' => $items->first()->ticket->client->name,
                'invoices' => $items->count(),
                'billed' => (int) $items->sum('amount'),
                'paid' => (int) $items->where('status', InvoiceStatus::Paid)->sum('amount'),
                'outstanding' => (int) $unpaid->sum('amount'),
                'overdue' => (int) $unpaid->filter(fn (Invoice $invoice): bool => $invoice->isOverdue($today))->sum('amount'),
                'oldest_due' => $unpaid->min('due_date')?->format('d/m/Y'),
            ];
        })->sortByDesc('outstanding')->values();

        return Inertia::render('Backend/Receivables', [
            'clients' => $clients,
            'totals' => [
                'billed' => (int) $clients->sum('billed'),
                'paid' => (int) $clients->sum('paid'),
                'outstanding' => (int) $clients->sum('outstanding'),
                'overdue' => (int) $clients->sum('overdue'),
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'from' => $filters['from'] ?? '',
                'until' => $filters['until'] ?? '',
            ],
            'urls' => ['export' => route('receivables.export', array_filter($filters), absolute: false)],
        ]);
    }

    /**
     * Ekspor rincian tagihan per klien sebagai CSV (UTF-8 dengan BOM agar terbaca Excel).
     */
    public function export(Request $request): StreamedResponse
    {
        $invoices = $this->invoices($this->filters($request))
            ->sortBy([fn (Invoice $a, Invoice $b): int => $a->ticket->client->name <=> $b->ticket->client->name, fn (Invoice $a, Invoice $b): int => $a->number <=> $b->number]);
        $today = now()->startOfDay();

        return response()->streamDownload(function () use ($invoices, $today): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Klien', 'Nomor tagihan', 'Tiket', 'Produk', 'Termin', 'Jumlah (Rp)', 'Tanggal terbit', 'Jatuh tempo', 'Status', 'Lewat jatuh tempo', 'Tanggal lunas', 'Metode bayar'], escape: '');

            foreach ($invoices as $invoice) {
                fputcsv($output, [
                    $invoice->ticket->client->name,
                    $invoice->number,
                    $invoice->ticket->code,
                    $invoice->ticket->product_name,
                    $invoice->label(),
                    $invoice->amount,
                    $invoice->created_at?->format('d/m/Y'),
                    $invoice->due_date->format('d/m/Y'),
                    $invoice->status->label(),
                    $invoice->isOverdue($today) ? 'Ya' : 'Tidak',
                    $invoice->paid_at?->format('d/m/Y H:i') ?? '',
                    $invoice->payment_type ?? '',
                ], escape: '');
            }

            fclose($output);
        }, 'laporan-piutang-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{search?: ?string, from?: ?string, until?: ?string} */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'from.date_format' => 'Format tanggal awal tidak valid.',
            'until.date_format' => 'Format tanggal akhir tidak valid.',
            'until.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);
    }

    /**
     * @param  array{search?: ?string, from?: ?string, until?: ?string}  $filters
     * @return Collection<int, Invoice>
     */
    private function invoices(array $filters): Collection
    {
        $search = $filters['search'] ?? null;

        return Invoice::query()->with('ticket.client')
            ->when(filled($search), fn (Builder $query) => $query->whereHas('ticket.client', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))
            ->when(filled($filters['from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when(filled($filters['until'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['until']))
            ->get();
    }
}
