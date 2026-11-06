<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan atau tambahan data BPOM (TD1–TD3) dengan tenggat 60 hari kerja (FR-29, FR-90, BR-23).
 */
#[Fillable(['ticket_id', 'bpom_submission_id', 'sequence', 'note', 'requested_on', 'deadline', 'responded_at', 'reminded_days', 'recorded_by'])]
class BpomRequest extends Model
{
    /** Batas tambahan data per pengajuan; TD3 yang tidak terpenuhi berarti pendaftaran ditolak. */
    public const MAX_SEQUENCE = 3;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'bpom_submission_id' => 'integer',
            'sequence' => 'integer',
            'requested_on' => 'date',
            'deadline' => 'date',
            'responded_at' => 'datetime',
            'reminded_days' => 'integer',
            'recorded_by' => 'integer',
        ];
    }

    /** @return BelongsTo<BpomSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(BpomSubmission::class, 'bpom_submission_id');
    }

    public function code(): string
    {
        return "TD{$this->sequence}";
    }
}
