<?php

namespace App\Services;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentRule;
use App\Models\DocumentType;
use App\Models\Ticket;

class ChecklistBuilder
{
    public function __construct(private DocumentValidator $validator) {}

    /**
     * Susun checklist kedua fase saat tiket dibuat (FR-11): semua jenis dokumen wajib yang
     * aktif, ditambah jenis kondisional yang aturannya berlaku untuk sediaan dan kategori
     * produk pada tanggal pembuatan tiket (BR-07). Dokumen bersama klien dan perusahaan yang
     * masih berlaku langsung ditautkan (FR-12). Dokumen per bahan baku ditambahkan saat R&D
     * memilih bahan (FR-14), sedangkan dokumen opsional tidak masuk otomatis.
     *
     * @return array{items: int, linked: int} jumlah butir checklist dan butir yang langsung tertaut
     */
    public function build(Ticket $ticket): array
    {
        $required = DocumentType::query()
            ->where('is_active', true)
            ->where('requirement', DocumentRequirement::Required)
            ->where('scope', '!=', DocumentScope::Material)
            ->orderBy('id')
            ->get();

        $rules = DocumentRule::query()
            ->appliesTo($ticket->dosageForm, $ticket->category, $ticket->created_at)
            ->whereHas('documentType', fn ($query) => $query
                ->where('is_active', true)
                ->where('requirement', DocumentRequirement::Conditional)
                ->where('scope', '!=', DocumentScope::Material))
            ->with('documentType')
            ->orderBy('id')
            ->get()
            ->unique('document_type_id');

        $items = $required->map(fn (DocumentType $type): array => $this->item($ticket, $type, null))
            ->concat($rules->map(fn (DocumentRule $rule): array => $this->item($ticket, $rule->documentType, $rule->id)));

        $ticket->documents()->createMany($items->all());

        return [
            'items' => $items->count(),
            'linked' => $items->whereNotNull('document_id')->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function item(Ticket $ticket, DocumentType $type, ?int $ruleId): array
    {
        $shared = $this->validSharedDocument($ticket, $type);

        return [
            'document_type_id' => $type->id,
            'phase' => $type->phase,
            'requirement' => $type->requirement,
            'document_rule_id' => $ruleId,
            'document_id' => $shared?->id,
            'status' => $shared === null ? DocumentStatus::Missing : DocumentStatus::UnderReview,
        ];
    }

    /**
     * Dokumen bersama milik klien tiket atau milik perusahaan yang versi terkininya lolos
     * validasi dan, bila diperiksa ulang hari ini, belum melewati umur maksimal maupun masa
     * berlakunya. Pemakaiannya pada tiket baru tetap diverifikasi ulang (BR-05).
     */
    private function validSharedDocument(Ticket $ticket, DocumentType $type): ?Document
    {
        if (! in_array($type->scope, [DocumentScope::Client, DocumentScope::Company], true)) {
            return null;
        }

        $document = Document::query()
            ->where('document_type_id', $type->id)
            ->where('scope', $type->scope)
            ->where('client_id', $type->scope === DocumentScope::Client ? $ticket->client_id : null)
            ->whereNotNull('current_version_id')
            ->with('currentVersion')
            ->first();

        $version = $document?->currentVersion;

        if ($version === null || ! $version->validation_passed) {
            return null;
        }

        return $this->validator->errors($type, $version->issued_at, $version->expires_at, now()) === [] ? $document : null;
    }
}
