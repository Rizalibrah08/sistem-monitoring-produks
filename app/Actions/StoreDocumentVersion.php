<?php

namespace App\Actions;

use App\DocumentScope;
use App\DocumentSource;
use App\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\TicketDocument;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentValidator;
use App\Services\RepairStage;
use App\Services\StaffNotifier;
use App\Services\TicketReviewFlagger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StoreDocumentVersion
{
    public function __construct(private DocumentValidator $validator, private AuditLogger $audit, private TicketReviewFlagger $reviews, private RepairStage $repair, private StaffNotifier $notifier) {}

    /**
     * Simpan unggahan sebagai versi baru dokumen butir checklist (FR-15), jalankan validasi
     * otomatis (FR-71), lalu perbarui status butir: lolos menjadi sedang diverifikasi, gagal
     * menjadi perlu revisi beserta alasannya (BR-19). Versi baru dokumen bersama menandai
     * tiket aktif lain yang memakainya sebagai perlu ditinjau (FR-20).
     *
     * @param  array{number: string, issued_at?: ?string, expires_at?: ?string}  $data
     */
    public function handle(TicketDocument $item, UploadedFile $file, array $data, ?User $actor, DocumentSource $source): DocumentVersion
    {
        $disk = Storage::disk('local');
        $storedPath = null;

        try {
            return DB::transaction(function () use ($item, $file, $data, $actor, $source, &$storedPath): DocumentVersion {
                $item->loadMissing(['ticket', 'documentType']);
                $document = $this->documentFor($item);
                $version = $this->createVersion($document, $item->documentType, $file, $data, $actor, $source, $storedPath);
                $errors = $version->validation_errors;
                $number = $version->version;
                $before = ['status' => $item->status->value];
                $item->update([
                    'document_id' => $document->id,
                    'status' => $errors === [] ? DocumentStatus::UnderReview : DocumentStatus::NeedsRevision,
                    'revision_reason' => $errors === [] ? null : implode(' ', $errors),
                ]);

                $this->audit->record('document.uploaded', $document, $before, [
                    'document_type' => $item->documentType->name,
                    'version' => $number,
                    'number' => $version->number,
                    'source' => $source->value,
                    'status' => $item->status->value,
                    'validation_errors' => $errors,
                ], source: $actor === null ? 'client' : 'web', actor: $actor, ticketCode: $item->ticket->code);

                $this->reviews->flagNewVersion($document, $version, $item->ticket_id);
                $this->repair->closeIfResolved($item->ticket);

                if ($errors === []) {
                    $this->notifier->documentAwaitingReview($item, $actor);
                } else {
                    $this->notifier->documentReturned($item, implode(' ', $errors), $actor);
                }

                return $version;
            }, attempts: 3);
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                $disk->delete($storedPath);
            }

            throw $exception;
        }
    }

    /**
     * Unggah versi baru dokumen bersama perusahaan dari halaman Dokumen bersama, tanpa butir
     * checklist tertentu (F05). Versi divalidasi otomatis dan tiket aktif yang memakainya
     * ditandai perlu ditinjau (FR-20).
     *
     * @param  array{number: string, issued_at?: ?string, expires_at?: ?string}  $data
     */
    public function handleShared(Document $document, UploadedFile $file, array $data, User $actor): DocumentVersion
    {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($document, $file, $data, $actor, &$storedPath): DocumentVersion {
                $document->loadMissing('documentType');
                $version = $this->createVersion($document, $document->documentType, $file, $data, $actor, DocumentSource::Internal, $storedPath);

                $this->audit->record('document.uploaded', $document, after: [
                    'document_type' => $document->documentType->name,
                    'version' => $version->version,
                    'number' => $version->number,
                    'source' => DocumentSource::Internal->value,
                    'validation_errors' => $version->validation_errors,
                ], actor: $actor);

                $this->reviews->flagNewVersion($document, $version, 0);

                return $version;
            }, attempts: 3);
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }
    }

    /**
     * Simpan berkas sebagai versi berikutnya dokumen beserta hasil validasi otomatisnya, lalu
     * jadikan versi terkini. Dokumen dikunci agar nomor versi tidak bertabrakan.
     *
     * @param  array{number: string, issued_at?: ?string, expires_at?: ?string}  $data
     */
    private function createVersion(Document $document, DocumentType $type, UploadedFile $file, array $data, ?User $actor, DocumentSource $source, ?string &$storedPath): DocumentVersion
    {
        Document::query()->whereKey($document->id)->lockForUpdate()->first();

        $issuedAt = filled($data['issued_at'] ?? null) ? Carbon::parse($data['issued_at']) : null;
        $expiresAt = filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at']) : null;
        $errors = $this->validator->errors($type, $issuedAt, $expiresAt, now());

        $number = (int) $document->versions()->max('version') + 1;
        $extension = strtolower($file->getClientOriginalExtension());
        $storedPath = Storage::disk('local')->putFileAs("documents/{$document->id}", $file, "v{$number}-".Str::uuid().".{$extension}");

        $version = $document->versions()->create([
            'version' => $number,
            'path' => $storedPath,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'number' => $data['number'],
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'source' => $source,
            'uploaded_by' => $actor?->id,
            'validation_passed' => $errors === [],
            'validation_errors' => $errors,
        ]);

        $document->update(['current_version_id' => $version->id]);

        return $version;
    }

    /**
     * Dokumen milik tiket, atau dokumen bersama milik klien, bahan baku, atau perusahaan
     * yang dipakai ulang lintas tiket (BR-03). Butir bahan baku tanpa bahan terpilih
     * diperlakukan sebagai dokumen tiket.
     */
    private function documentFor(TicketDocument $item): Document
    {
        if ($item->document_id !== null) {
            return Document::query()->findOrFail($item->document_id);
        }

        $scope = $item->documentType->scope;
        $owner = match (true) {
            $scope === DocumentScope::Client => ['client_id' => $item->ticket->client_id],
            $scope === DocumentScope::Company => [],
            $scope === DocumentScope::Material && $item->material_id !== null => ['material_id' => $item->material_id],
            default => ['ticket_id' => $item->ticket_id],
        };

        return Document::query()->firstOrCreate([
            'document_type_id' => $item->document_type_id,
            'scope' => $scope === DocumentScope::Material && $item->material_id === null ? DocumentScope::Ticket : $scope,
            'ticket_id' => $owner['ticket_id'] ?? null,
            'client_id' => $owner['client_id'] ?? null,
            'material_id' => $owner['material_id'] ?? null,
        ]);
    }
}
