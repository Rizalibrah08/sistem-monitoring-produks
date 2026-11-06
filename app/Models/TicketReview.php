<?php

namespace App\Models;

use App\ReviewTrigger;
use Database\Factories\TicketReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alasan tiket aktif perlu ditinjau RA karena dokumen bersama yang dipakainya berubah
 * atau mendekati kedaluwarsa (FR-20). Satu catatan per tiket, versi dokumen, dan pemicu.
 */
#[Fillable(['ticket_id', 'document_id', 'document_version_id', 'trigger', 'message', 'resolved_at', 'resolved_by'])]
class TicketReview extends Model
{
    /** @use HasFactory<TicketReviewFactory> */
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
            'document_id' => 'integer',
            'document_version_id' => 'integer',
            'trigger' => ReviewTrigger::class,
            'resolved_at' => 'datetime',
            'resolved_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }
}
