<?php

namespace App\Http\Controllers;

use App\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    /** Filter status termasuk "lewat jatuh tempo" yang diturunkan dari tanggal. */
    public const OVERDUE = 'lewat_jatuh_tempo';

    /**
     * Daftar seluruh tagihan untuk Finance dan Manajer, termasuk tagihan tiket yang sudah
     * ditutup selama belum lunas (FR-79, BR-17).
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', [...array_column(InvoiceStatus::cases(), 'value'), self::OVERDUE])],
        ]);
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $today = now()->startOfDay();

        $invoices = Invoice::query()->with(['ticket.client', 'contract'])
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('ticket', fn (Builder $query) => $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('client', fn (Builder $query) => $query->where('name', 'like', "%{$search}%")))))
            ->when($status === self::OVERDUE, fn (Builder $query) => $query->where('status', '!=', InvoiceStatus::Paid)->whereDate('due_date', '<', $today))
            ->when(filled($status) && $status !== self::OVERDUE, fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw('case when status = ? then 1 else 0 end', [InvoiceStatus::Paid->value])
            ->orderBy('due_date')->orderBy('id')
            ->paginate(25)->withQueryString()
            ->through(fn (Invoice $invoice): array => self::row($invoice));

        $unpaid = Invoice::query()->where('status', '!=', InvoiceStatus::Paid);

        return Inertia::render('Backend/Invoices', [
            'invoices' => $invoices,
            'filters' => ['search' => $search ?? '', 'status' => $status ?? ''],
            'statuses' => [
                ...array_map(fn (InvoiceStatus $case): array => ['value' => $case->value, 'label' => $case->label()], InvoiceStatus::cases()),
                ['value' => self::OVERDUE, 'label' => 'Lewat jatuh tempo'],
            ],
            'summary' => [
                'outstanding' => (int) (clone $unpaid)->sum('amount'),
                'unpaid_count' => (clone $unpaid)->count(),
                'overdue_count' => (clone $unpaid)->whereDate('due_date', '<', $today)->count(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public static function row(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'term_label' => $invoice->label(),
            'amount' => $invoice->amount,
            'due_date' => $invoice->due_date->format('d/m/Y'),
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'is_overdue' => $invoice->isOverdue(now()),
            'paid_at' => $invoice->paid_at?->format('d/m/Y H:i'),
            'payment_type' => $invoice->payment_type,
            'ticket_code' => $invoice->ticket->code,
            'client' => $invoice->ticket->client->name,
            'ticket_url' => route('tickets.show', $invoice->ticket_id, absolute: false),
        ];
    }
}
