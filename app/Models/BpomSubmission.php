<?php

namespace App\Models;

use App\StagePhase;
use App\SubmissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pengajuan satu fase ke BPOM beserta PNBP (FR-28, BR-13). Versi dokumen yang diajukan
 * dikunci di tabel pivot sehingga tidak ikut berubah saat dokumen bersama mendapat versi
 * baru (BR-06).
 */
#[Fillable(['ticket_id', 'phase', 'submitted_on', 'pnbp', 'status', 'result_on', 'result_path', 'result_original_name', 'registration_deadline', 'reminded_days', 'recorded_by'])]
class BpomSubmission extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'phase' => StagePhase::class,
            'submitted_on' => 'date',
            'pnbp' => 'integer',
            'status' => SubmissionStatus::class,
            'result_on' => 'date',
            'registration_deadline' => 'date',
            'reminded_days' => 'integer',
            'recorded_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsToMany<DocumentVersion, $this> */
    public function lockedVersions(): BelongsToMany
    {
        return $this->belongsToMany(DocumentVersion::class, 'bpom_submission_documents')->withPivot('ticket_document_id');
    }

    /** @return HasMany<BpomRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(BpomRequest::class);
    }
}
