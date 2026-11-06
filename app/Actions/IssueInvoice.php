<?php

namespace App\Actions;

use App\InvoiceStatus;
use App\InvoiceTerm;
use App\Models\Contract;
use App\Models\Invoice;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class IssueInvoice
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Terbitkan tagihan satu termin kontrak berkode INV/TAHUN/NOMOR dengan jatuh tempo
     * sesuai kontrak (FR-75). Termin yang sudah ditagih tidak diterbitkan ulang.
     */
    public function handle(Contract $contract, InvoiceTerm $term): Invoice
    {
        return DB::transaction(function () use ($contract, $term): Invoice {
            $existing = Invoice::query()->where('contract_id', $contract->id)->where('term', $term)->first();

            if ($existing !== null) {
                return $existing;
            }

            $invoice = Invoice::query()->create([
                'ticket_id' => $contract->ticket_id,
                'contract_id' => $contract->id,
                'number' => $this->nextNumber(),
                'term' => $term,
                'percent' => $term === InvoiceTerm::DownPayment ? $contract->dp_percent : $contract->settlementPercent(),
                'amount' => $term === InvoiceTerm::DownPayment ? $contract->dpAmount() : $contract->settlementAmount(),
                'due_date' => now()->addDays($contract->payment_due_days)->toDateString(),
                'status' => InvoiceStatus::Issued,
            ]);

            $this->audit->record('invoice.issued', $invoice, after: [
                'number' => $invoice->number,
                'term' => $term->value,
                'amount' => $invoice->amount,
                'due_date' => $invoice->due_date->toDateString(),
            ], source: 'system', ticketCode: $contract->ticket->code);

            return $invoice;
        }, attempts: 5);
    }

    /** Nomor urut per tahun; tagihan terakhir dikunci agar penerbitan bersamaan tidak bertabrakan. */
    private function nextNumber(): string
    {
        $prefix = 'INV/'.now()->year.'/';
        $last = Invoice::query()->where('number', 'like', $prefix.'%')->orderByDesc('number')->lockForUpdate()->value('number');
        $number = $last === null ? 1 : (int) substr($last, strlen($prefix)) + 1;

        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
