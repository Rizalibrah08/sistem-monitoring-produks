<?php

namespace App\Models;

use App\ContractStatus;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kontrak (MoU) satu tiket dari templat Word (FR-73, BR-15): nilai jasa dengan termin
 * DP dan pelunasan yang persentasenya dapat diubah per kontrak.
 */
#[Fillable([
    'ticket_id', 'number', 'service_value', 'dp_percent', 'payment_due_days', 'status', 'document_template_id', 'template_version_id',
    'word_path', 'pdf_path', 'generated_at', 'generation_error', 'signed_path', 'signed_original_name', 'signed_at',
    'sent_at', 'confirmed_at', 'confirmed_by', 'created_by',
])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    /** Persentase DP bawaan; pelunasan adalah sisanya (BR-15). */
    public const DEFAULT_DP_PERCENT = 50;

    public const DEFAULT_PAYMENT_DUE_DAYS = 7;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'service_value' => 'integer',
            'dp_percent' => 'integer',
            'payment_due_days' => 'integer',
            'status' => ContractStatus::class,
            'document_template_id' => 'integer',
            'template_version_id' => 'integer',
            'generated_at' => 'datetime',
            'signed_at' => 'datetime',
            'sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'confirmed_by' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<DocumentTemplate, $this> */
    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    /** @return BelongsTo<TemplateVersion, $this> */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function settlementPercent(): int
    {
        return 100 - $this->dp_percent;
    }

    public function dpAmount(): int
    {
        return intdiv($this->service_value * $this->dp_percent, 100);
    }

    public function settlementAmount(): int
    {
        return $this->service_value - $this->dpAmount();
    }
}
