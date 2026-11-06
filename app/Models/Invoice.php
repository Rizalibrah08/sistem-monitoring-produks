<?php

namespace App\Models;

use App\InvoiceStatus;
use App\InvoiceTerm;
use Carbon\CarbonInterface;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tagihan termin kontrak (FR-75, BR-17) yang dibayar klien melalui Midtrans Snap.
 * Tagihan tetap dipantau sampai lunas walaupun tiketnya sudah ditutup.
 */
#[Fillable(['ticket_id', 'contract_id', 'number', 'term', 'percent', 'amount', 'due_date', 'status', 'paid_at', 'payment_type'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'contract_id' => 'integer',
            'term' => InvoiceTerm::class,
            'percent' => 'integer',
            'amount' => 'integer',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return HasMany<InvoicePayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function isOverdue(CarbonInterface $today): bool
    {
        return $this->status !== InvoiceStatus::Paid && $this->due_date->lt($today->copy()->startOfDay());
    }

    public function label(): string
    {
        return "{$this->term->label()} {$this->percent}%";
    }
}
