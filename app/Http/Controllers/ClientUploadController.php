<?php

namespace App\Http\Controllers;

use App\Actions\StoreDocumentVersion;
use App\Actions\StoreSignedContract;
use App\ContractStatus;
use App\DocumentSource;
use App\DocumentStatus;
use App\Http\Requests\ClientDocumentUploadRequest;
use App\Http\Requests\ClientSignedContractRequest;
use App\Models\Contract;
use App\Models\DocumentType;
use App\Models\TicketDocument;
use App\Models\UploadToken;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ClientUploadController extends Controller
{
    /**
     * Halaman unggah klien tanpa login (FR-18): hanya dokumen ber-PIC klien pada tiket dan
     * fase token tersebut. Token yang tidak dikenal, dicabut, atau kedaluwarsa menampilkan
     * halaman tautan tidak berlaku tanpa membuka data tiket apa pun.
     */
    public function show(Request $request, string $token): Response
    {
        $uploadToken = $this->usableToken($token);

        if ($uploadToken === null) {
            return $this->private(Inertia::render('Client/UploadLinkExpired')->toResponse($request)->setStatusCode(410));
        }

        $ticket = $uploadToken->ticket;
        $isActive = $ticket->status === TicketStatus::Active;
        $items = $this->clientItems($uploadToken);
        $approved = $items->filter(fn (TicketDocument $item): bool => $item->status === DocumentStatus::Approved)->count();

        return $this->private(Inertia::render('Client/UploadLink', [
            'ticket' => [
                'code' => $ticket->code,
                'product_name' => $ticket->product_name,
                'client' => $ticket->client->name,
                'client_pic' => $ticket->client->pic_name,
                'marketing_user' => $ticket->client->marketingUser->name,
                'phase_label' => $uploadToken->phase->label(),
                'is_active' => $isActive,
            ],
            'expires_at' => $uploadToken->expires_at->format('d/m/Y H:i'),
            'contract' => ($contract = $ticket->contract) === null || $contract->status === ContractStatus::Draft ? null : [
                'number' => $contract->number,
                'status' => $contract->status->value,
                'status_label' => $contract->status->label(),
                'signed_at' => $contract->signed_at?->format('d/m/Y H:i'),
                'can_sign' => $isActive && in_array($contract->status, [ContractStatus::Sent, ContractStatus::Signed], true),
                'download_url' => route('client-uploads.contract', $token, absolute: false),
                'upload_url' => route('client-uploads.contract.signed', $token, absolute: false),
            ],
            'progress' => $items->isEmpty() ? 0 : (int) round($approved / $items->count() * 100),
            'documents' => $items->map(fn (TicketDocument $item): array => [
                'id' => $item->id,
                'name' => $item->documentType->name,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'revision_reason' => $item->revision_reason,
                'requires_issued_at' => $item->documentType->max_age_months !== null,
                'requires_expires_at' => $item->documentType->requires_expiry_date,
                'can_upload' => $isActive && $item->status !== DocumentStatus::Approved,
                'upload_url' => route('client-uploads.store', [$token, $item], absolute: false),
                'current_version' => ($version = $item->document?->currentVersion) === null ? null : [
                    'version' => $version->version,
                    'original_name' => $version->original_name,
                    'uploaded_at' => $version->created_at?->format('d/m/Y H:i'),
                ],
            ])->values(),
        ])->toResponse($request));
    }

    /**
     * Unggahan klien menjadi versi baru bersumber klien dan langsung divalidasi otomatis (US-03).
     */
    public function store(ClientDocumentUploadRequest $request, string $token, TicketDocument $ticketDocument, StoreDocumentVersion $store): RedirectResponse
    {
        $uploadToken = $this->usableToken($token);

        if ($uploadToken === null) {
            return redirect()->route('client-uploads.show', $token);
        }

        $item = $this->clientItems($uploadToken)->firstWhere('id', $ticketDocument->id);
        abort_if($item === null || $uploadToken->ticket->status !== TicketStatus::Active || $item->status === DocumentStatus::Approved, 404);

        $version = $store->handle($item, $request->file('file'), $request->safe()->only(['number', 'issued_at', 'expires_at']), null, DocumentSource::Client);
        $uploadToken->update(['last_used_at' => now()]);

        return back()->with('status', $version->validation_passed
            ? "{$item->documentType->name} diterima dan sedang diverifikasi tim kami."
            : "{$item->documentType->name} diterima, tetapi perlu diperbaiki: ".implode(' ', $version->validation_errors));
    }

    /**
     * Klien mengunduh kontrak yang sudah dikirim untuk ditandatangani (FR-70, FR-74).
     */
    public function contract(Request $request, string $token): Response
    {
        $contract = $this->sentContract($token);
        $path = $contract->pdf_path ?? $contract->word_path;
        abort_if($path === null, 404);

        return $this->private(Storage::disk('local')->download($path, 'Kontrak '.str_replace('/', '-', $contract->number).'.'.pathinfo($path, PATHINFO_EXTENSION)));
    }

    /**
     * Klien mengunggah kontrak bertanda tangan lewat tautan (FR-74).
     */
    public function signContract(ClientSignedContractRequest $request, string $token, StoreSignedContract $store): RedirectResponse
    {
        $contract = $this->sentContract($token);
        abort_unless($contract->ticket->status === TicketStatus::Active, 404);
        $store->handle($contract, $request->file('file'), null);
        $this->usableToken($token)?->update(['last_used_at' => now()]);

        return back()->with('status', 'Kontrak bertanda tangan diterima dan menunggu konfirmasi tim kami.');
    }

    private function sentContract(string $token): Contract
    {
        $contract = $this->usableToken($token)?->ticket->contract;
        abort_unless($contract !== null && in_array($contract->status, [ContractStatus::Sent, ContractStatus::Signed], true), 404);

        return $contract;
    }

    private function usableToken(string $token): ?UploadToken
    {
        if (strlen($token) !== UploadToken::TOKEN_LENGTH) {
            return null;
        }

        return UploadToken::query()->usable()->where('token_hash', UploadToken::hash($token))
            ->with(['ticket.client.marketingUser'])->first();
    }

    /** @return Collection<int, TicketDocument> */
    private function clientItems(UploadToken $uploadToken): Collection
    {
        return $uploadToken->ticket->documents()
            ->whereHas('documentType', fn (Builder $query) => $query->where('pic_role', DocumentType::CLIENT_PIC))
            ->with(['ticket', 'documentType', 'document.currentVersion'])
            ->get()
            ->filter(fn (TicketDocument $item): bool => $item->belongsToPhase($uploadToken->phase))
            ->sortBy(fn (TicketDocument $item): string => $item->documentType->name)
            ->values();
    }

    /** Token ada di URL: jangan disimpan cache, diindeks, atau bocor lewat header Referer. */
    private function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
