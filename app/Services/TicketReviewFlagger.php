<?php

namespace App\Services;

use App\DocumentScope;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Ticket;
use App\ReviewTrigger;
use App\TicketStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class TicketReviewFlagger
{
    /** Batas hari menjelang kedaluwarsa yang memicu peninjauan (FR-20). */
    public const EXPIRY_WINDOW_DAYS = 30;

    public function __construct(private AuditLogger $audit) {}

    /**
     * Tandai tiket aktif lain yang memakai dokumen bersama ini setelah dokumen mendapat
     * versi baru (FR-20). Tiket tempat versi diunggah tidak ditandai karena butirnya
     * sudah diperbarui oleh unggahan itu sendiri.
     *
     * @return int jumlah tiket yang ditandai
     */
    public function flagNewVersion(Document $document, DocumentVersion $version, int $uploadedOnTicketId): int
    {
        if ($document->scope === DocumentScope::Ticket || $version->version < 2) {
            return 0;
        }

        $document->loadMissing('documentType');

        return $this->flag($document, $version, ReviewTrigger::NewVersion,
            "Dokumen bersama {$document->documentType->name} mendapat versi {$version->version}.",
            exceptTicketId: $uploadedOnTicketId);
    }

    /**
     * Tandai tiket aktif yang memakai dokumen bersama dengan versi terkini yang kedaluwarsa
     * dalam 30 hari ke depan atau sudah kedaluwarsa (FR-20). Setiap versi hanya menandai
     * satu kali per tiket, sehingga command harian aman dijalankan berulang.
     *
     * @return int jumlah tiket yang ditandai
     */
    public function flagExpiring(CarbonInterface $today): int
    {
        $limit = $today->copy()->startOfDay()->addDays(self::EXPIRY_WINDOW_DAYS)->toDateString();

        $documents = Document::query()
            ->where('scope', '!=', DocumentScope::Ticket)
            ->whereHas('currentVersion', fn (Builder $query) => $query->whereNotNull('expires_at')->whereDate('expires_at', '<=', $limit))
            ->with(['documentType', 'currentVersion'])
            ->get();

        return $documents->sum(fn (Document $document): int => $this->flag($document, $document->currentVersion, ReviewTrigger::ExpiringSoon,
            "Dokumen bersama {$document->documentType->name} berlaku sampai {$document->currentVersion->expires_at->format('d/m/Y')}."));
    }

    private function flag(Document $document, DocumentVersion $version, ReviewTrigger $trigger, string $message, ?int $exceptTicketId = null): int
    {
        $tickets = Ticket::query()
            ->where('status', TicketStatus::Active)
            ->whereHas('documents', fn (Builder $query) => $query->where('document_id', $document->id))
            ->when($exceptTicketId !== null, fn (Builder $query) => $query->whereKeyNot($exceptTicketId))
            ->whereDoesntHave('reviews', fn (Builder $query) => $query->where('document_version_id', $version->id)->where('trigger', $trigger))
            ->get();

        foreach ($tickets as $ticket) {
            $review = $ticket->reviews()->create([
                'document_id' => $document->id,
                'document_version_id' => $version->id,
                'trigger' => $trigger,
                'message' => $message,
            ]);

            $this->audit->record('ticket.review_flagged', $review, after: [
                'trigger' => $trigger->value,
                'document_type' => $document->documentType->name,
                'version' => $version->version,
            ], source: 'system', ticketCode: $ticket->code);
        }

        return $tickets->count();
    }
}
