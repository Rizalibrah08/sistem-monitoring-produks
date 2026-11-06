<?php

namespace App\Actions;

use App\DocumentStatus;
use App\Models\DocumentReview;
use App\Models\TicketDocument;
use App\Models\User;
use App\ReviewDecision;
use App\ReviewLevel;
use App\Services\AuditLogger;
use App\Services\RepairStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewTicketDocument
{
    public function __construct(private AuditLogger $audit, private RepairStage $repair) {}

    /**
     * Catat keputusan verifikasi manual atas versi terkini butir checklist. QA/QC memeriksa
     * isi dokumen mutu (FR-24), lalu RA menyetujui atau mengembalikan pemakaiannya pada
     * tiket (FR-25). Pengembalian membuat butir perlu revisi dengan alasan dan membuka tahap
     * perbaikan (FR-26). Keputusan ditolak bila butir sudah berubah sejak halaman dimuat (FR-33).
     */
    public function handle(TicketDocument $item, ReviewLevel $level, ReviewDecision $decision, ?string $note, User $actor, string $token): DocumentReview
    {
        return DB::transaction(function () use ($item, $level, $decision, $note, $actor, $token): DocumentReview {
            $item = TicketDocument::query()->whereKey($item->id)->lockForUpdate()->with(['ticket', 'documentType', 'document.currentVersion'])->firstOrFail();

            if (! hash_equals($item->concurrencyToken(), $token)) {
                throw ValidationException::withMessages(['token' => 'Dokumen ini sudah diubah pengguna lain. Muat ulang halaman lalu periksa kembali.']);
            }

            $version = $item->document?->currentVersion;

            if ($item->status !== DocumentStatus::UnderReview || $version === null || ! $version->validation_passed) {
                throw ValidationException::withMessages(['token' => 'Dokumen ini tidak sedang menunggu verifikasi.']);
            }

            if ($level === ReviewLevel::Content && ! $item->needsContentReview()) {
                throw ValidationException::withMessages(['token' => 'Isi dokumen ini tidak memerlukan verifikasi QA/QC.']);
            }

            if ($level === ReviewLevel::Usage && $item->needsContentReview() && ! $item->contentApproved()) {
                throw ValidationException::withMessages(['token' => 'Isi dokumen mutu harus diverifikasi QA/QC terlebih dahulu.']);
            }

            $review = DocumentReview::query()->create([
                'document_version_id' => $version->id,
                'ticket_document_id' => $item->id,
                'level' => $level,
                'decision' => $decision,
                'note' => filled($note) ? $note : null,
                'reviewed_by' => $actor->id,
            ]);

            $before = ['status' => $item->status->value];

            if ($decision === ReviewDecision::NeedsRevision) {
                $item->update(['status' => DocumentStatus::NeedsRevision, 'revision_reason' => $note]);
                $this->repair->open($item->ticket);
            } elseif ($level === ReviewLevel::Usage) {
                $item->update(['status' => DocumentStatus::Approved, 'revision_reason' => null]);
            } else {
                $item->touch();
            }

            $this->audit->record('document.reviewed', $review, $before, [
                'document_type' => $item->documentType->name,
                'version' => $version->version,
                'level' => $level->value,
                'decision' => $decision->value,
                'note' => $review->note,
                'status' => $item->status->value,
            ], actor: $actor, ticketCode: $item->ticket->code);

            return $review;
        });
    }
}
