<?php

namespace App\Services;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentStatus;
use App\Models\DocumentRule;
use App\Models\DocumentType;
use App\Models\Ticket;

class ChecklistBuilder
{
    /**
     * Susun checklist kedua fase saat tiket dibuat (FR-11): semua jenis dokumen wajib yang
     * aktif, ditambah jenis kondisional yang aturannya berlaku untuk sediaan dan kategori
     * produk pada tanggal pembuatan tiket (BR-07). Dokumen per bahan baku ditambahkan saat
     * R&D memilih bahan (FR-14), sedangkan dokumen opsional tidak masuk otomatis.
     *
     * @return int jumlah butir checklist yang dibuat
     */
    public function build(Ticket $ticket): int
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

        $items = $required->map(fn (DocumentType $type): array => $this->item($type, null))
            ->concat($rules->map(fn (DocumentRule $rule): array => $this->item($rule->documentType, $rule->id)));

        $ticket->documents()->createMany($items->all());

        return $items->count();
    }

    /** @return array<string, mixed> */
    private function item(DocumentType $type, ?int $ruleId): array
    {
        return [
            'document_type_id' => $type->id,
            'phase' => $type->phase,
            'requirement' => $type->requirement,
            'document_rule_id' => $ruleId,
            'status' => DocumentStatus::Missing,
        ];
    }
}
