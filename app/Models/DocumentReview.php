<?php

namespace App\Models;

use App\ReviewDecision;
use App\ReviewLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu keputusan verifikasi manual atas versi dokumen pada butir checklist (F06).
 * Persetujuan isi berlaku untuk versinya, sehingga dokumen bersama yang sudah diperiksa
 * QA/QC tidak perlu diperiksa ulang di tiket lain; persetujuan pemakaian berlaku per tiket.
 */
#[Fillable(['document_version_id', 'ticket_document_id', 'level', 'decision', 'note', 'reviewed_by'])]
class DocumentReview extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_version_id' => 'integer',
            'ticket_document_id' => 'integer',
            'level' => ReviewLevel::class,
            'decision' => ReviewDecision::class,
            'reviewed_by' => 'integer',
        ];
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    /** @return BelongsTo<TicketDocument, $this> */
    public function ticketDocument(): BelongsTo
    {
        return $this->belongsTo(TicketDocument::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
