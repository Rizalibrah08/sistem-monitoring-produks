<?php

namespace App\Http\Controllers;

use App\Actions\StoreDocumentVersion;
use App\DocumentScope;
use App\Http\Requests\SharedDocumentUploadRequest;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SharedDocumentController extends Controller
{
    /**
     * Dokumen bersama klien, bahan baku, dan perusahaan beserta riwayat versi, masa berlaku,
     * dan jumlah tiket aktif yang memakainya (F05, BR-03).
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate(['scope' => ['nullable', Rule::in([DocumentScope::Client->value, DocumentScope::Material->value, DocumentScope::Company->value])]]);

        $documents = Document::query()
            ->where('scope', '!=', DocumentScope::Ticket)
            ->when(filled($filters['scope'] ?? null), fn (Builder $query) => $query->where('scope', $filters['scope']))
            ->whereNotNull('current_version_id')
            ->with(['documentType', 'client', 'material', 'currentVersion', 'versions' => fn ($query) => $query->orderByDesc('version')->with('uploader')])
            ->withCount(['ticketDocuments as active_tickets' => fn (Builder $query) => $query->whereHas('ticket', fn (Builder $query) => $query->where('status', TicketStatus::Active))])
            ->get()
            ->sortBy(fn (Document $document): string => $document->documentType->name.'|'.$this->owner($document))
            ->values()
            ->map(fn (Document $document): array => [
                'id' => $document->id,
                'name' => $document->documentType->name,
                'scope' => $document->scope->label(),
                'owner' => $this->owner($document),
                'active_tickets' => $document->active_tickets,
                'current' => $this->version($document->currentVersion),
                'expiring' => $document->currentVersion->expires_at !== null && $document->currentVersion->expires_at->lte(now()->addDays(30)),
                'versions' => $document->versions->map(fn (DocumentVersion $version): array => $this->version($version)),
            ]);

        return Inertia::render('Backend/SharedDocuments', [
            'documents' => $documents,
            'filters' => ['scope' => $filters['scope'] ?? ''],
            'companyTypes' => DocumentType::query()->where('scope', DocumentScope::Company)->where('is_active', true)->orderBy('name')->get()
                ->map(fn (DocumentType $type): array => ['value' => $type->id, 'label' => $type->name, 'requires_issued_at' => $type->max_age_months !== null, 'requires_expires_at' => $type->requires_expiry_date]),
            'urls' => ['store' => route('shared_documents.store', absolute: false)],
        ]);
    }

    /**
     * Unggah versi baru dokumen bersama perusahaan; dokumennya dibuat bila belum ada.
     */
    public function store(SharedDocumentUploadRequest $request, StoreDocumentVersion $store): RedirectResponse
    {
        $document = Document::query()->firstOrCreate([
            'document_type_id' => (int) $request->validated('document_type_id'),
            'scope' => DocumentScope::Company,
            'ticket_id' => null,
            'client_id' => null,
            'material_id' => null,
        ]);

        $version = $store->handleShared($document, $request->file('file'), $request->safe()->only(['number', 'issued_at', 'expires_at']), $request->user());

        return back()->with('status', $version->validation_passed
            ? "Versi {$version->version} tersimpan dan lolos validasi otomatis."
            : "Versi {$version->version} tersimpan, tetapi perlu revisi: ".implode(' ', $version->validation_errors));
    }

    private function owner(Document $document): string
    {
        return match ($document->scope) {
            DocumentScope::Client => $document->client?->name ?? '—',
            DocumentScope::Material => $document->material?->name ?? '—',
            default => (string) config('document_templates.company_name'),
        };
    }

    /** @return array<string, mixed> */
    private function version(DocumentVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'number' => $version->number,
            'issued_at' => $version->issued_at?->format('d/m/Y'),
            'expires_at' => $version->expires_at?->format('d/m/Y'),
            'validation_passed' => $version->validation_passed,
            'uploaded_by' => $version->uploader?->name ?? 'Klien',
            'uploaded_at' => $version->created_at?->format('d/m/Y H:i'),
            'download_url' => route('document-versions.download', $version, absolute: false),
        ];
    }
}
