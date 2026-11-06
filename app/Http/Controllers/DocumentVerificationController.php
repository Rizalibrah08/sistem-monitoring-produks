<?php

namespace App\Http\Controllers;

use App\Actions\ReviewTicketDocument;
use App\DocumentStatus;
use App\Http\Requests\ReviewTicketDocumentRequest;
use App\Models\DocumentType;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\ReviewDecision;
use App\ReviewLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentVerificationController extends Controller
{
    /** Batas butir yang dimuat per antrean. */
    private const QUEUE_LIMIT = 200;

    /**
     * Antrean verifikasi isi dokumen mutu oleh QA/QC (FR-24).
     */
    public function quality(Request $request): Response
    {
        return $this->queue($request, ReviewLevel::Content);
    }

    /**
     * Antrean persetujuan pemakaian dokumen oleh RA; hanya dokumen yang lolos validasi
     * otomatis (BR-19) dan, untuk dokumen mutu, sudah diverifikasi isinya (FR-25).
     */
    public function usage(Request $request): Response
    {
        return $this->queue($request, ReviewLevel::Usage);
    }

    public function store(ReviewTicketDocumentRequest $request, TicketDocument $ticketDocument, string $level, ReviewTicketDocument $review): RedirectResponse
    {
        $decision = ReviewDecision::from($request->validated('decision'));
        $review->handle($ticketDocument, ReviewLevel::from($level), $decision, $request->validated('note'), $request->user(), $request->validated('token'));

        return back()->with('status', $decision === ReviewDecision::Approved
            ? "{$ticketDocument->documentType->name} disetujui."
            : "{$ticketDocument->documentType->name} dikembalikan untuk revisi.");
    }

    private function queue(Request $request, ReviewLevel $level): Response
    {
        $user = $request->user();

        $items = TicketDocument::query()
            ->where('status', DocumentStatus::UnderReview)
            ->whereHas('ticket', fn (Builder $query) => $query->visibleTo($user))
            ->whereHas('document.currentVersion', fn (Builder $query) => $query->where('validation_passed', true))
            ->when($level === ReviewLevel::Content, fn (Builder $query) => $query->whereHas('documentType', fn (Builder $query) => $query->where('verifier_role', 'qa')))
            ->with(['ticket', 'documentType', 'material', 'document.currentVersion.uploader'])
            ->orderBy('updated_at')->orderBy('id')
            ->limit(self::QUEUE_LIMIT)->get()
            ->filter(fn (TicketDocument $item): bool => $level === ReviewLevel::Content
                ? ! $item->contentApproved()
                : ! $item->needsContentReview() || $item->contentApproved())
            ->values();

        return Inertia::render('Backend/Verification', [
            'level' => $level->value,
            'title' => $level === ReviewLevel::Content ? 'Verifikasi dokumen mutu' : 'Verifikasi dokumen',
            'description' => $level === ReviewLevel::Content
                ? 'Periksa isi dokumen mutu sebelum Regulatory Affairs menyetujui pemakaiannya.'
                : 'Setujui atau kembalikan pemakaian dokumen pada tiket. Dokumen yang gagal validasi otomatis tidak masuk antrean ini.',
            'items' => $items->map(fn (TicketDocument $item): array => self::row($item, $level)),
        ]);
    }

    /** @return array<string, mixed> */
    private static function row(TicketDocument $item, ReviewLevel $level): array
    {
        /** @var Ticket $ticket */
        $ticket = $item->ticket;
        $version = $item->document->currentVersion;

        return [
            'id' => $item->id,
            'ticket_code' => $ticket->code,
            'product_name' => $ticket->product_name,
            'ticket_url' => route('tickets.show', $ticket, absolute: false),
            'name' => $item->documentType->name,
            'material' => $item->material?->name,
            'phase_label' => $item->phase->label(),
            'pic' => DocumentType::roleLabel($item->documentType->pic_role),
            'content_verified' => $item->needsContentReview() ? $item->contentApproved() : null,
            'version' => [
                'version' => $version->version,
                'number' => $version->number,
                'issued_at' => $version->issued_at?->format('d/m/Y'),
                'expires_at' => $version->expires_at?->format('d/m/Y'),
                'uploaded_at' => $version->created_at?->format('d/m/Y H:i'),
                'uploaded_by' => $version->uploader?->name ?? 'Klien',
                'download_url' => route('document-versions.download', $version, absolute: false),
            ],
            'token' => $item->concurrencyToken(),
            'review_url' => route('ticket-documents.reviews.store', [$item, $level->value], absolute: false),
        ];
    }
}
