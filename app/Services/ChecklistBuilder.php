<?php

namespace App\Services;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentRule;
use App\Models\DocumentType;
use App\Models\Material;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\StagePhase;
use App\SubmissionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
        $items = $this->applicableTypes($ticket, material: false)
            ->map(fn (array $entry): array => $this->item($ticket, $entry['type'], $entry['rule_id'], null));

        return $this->store($ticket, $items);
    }

    /**
     * Tambahkan butir checklist dokumen bercakupan bahan baku untuk setiap bahan yang baru
     * dipilih (FR-14), mengikuti jenis wajib dan aturan kondisional yang berlaku saat tiket
     * dibuat. Dokumen asal peredaran bahan yang sudah ada dan masih berlaku langsung tertaut.
     *
     * @param  Collection<int, Material>  $materials
     * @return array{items: int, linked: int}
     */
    public function addMaterials(Ticket $ticket, Collection $materials): array
    {
        $types = $this->applicableTypes($ticket, material: true);
        $items = $materials->flatMap(fn (Material $material): Collection => $types
            ->map(fn (array $entry): array => $this->item($ticket, $entry['type'], $entry['rule_id'], $material)));

        return $this->store($ticket, $items);
    }

    /**
     * Perbedaan checklist tiket dengan jenis dokumen wajib dan aturan yang berlaku pada
     * tanggal tertentu (FR-21): butir yang perlu ditambahkan, dan butir otomatis yang tidak
     * lagi diwajibkan serta belum memiliki berkas sehingga aman dilepas. Fase yang sudah
     * diajukan ke BPOM tidak diubah karena versinya terkunci (BR-06).
     *
     * @return array{missing: Collection<int, array{type: DocumentType, rule_id: ?int, material: ?Material}>, obsolete: Collection<int, TicketDocument>}
     */
    public function differences(Ticket $ticket, CarbonInterface $asOf): array
    {
        $ticket->loadMissing(['documents.documentType', 'materials', 'bpomSubmissions']);
        $locked = $ticket->bpomSubmissions->where('status', '!=', SubmissionStatus::Expired)->pluck('phase')->unique()->all();
        $open = fn (StagePhase $phase): bool => ! in_array($phase, $locked, true) && ($phase !== StagePhase::BothPhases || $locked === []);

        $expected = $this->applicableTypes($ticket, material: false, asOf: $asOf)
            ->map(fn (array $entry): array => [...$entry, 'material' => null])
            ->concat($ticket->materials->flatMap(fn (Material $material): Collection => $this->applicableTypes($ticket, material: true, asOf: $asOf)
                ->map(fn (array $entry): array => [...$entry, 'material' => $material])))
            ->filter(fn (array $entry): bool => $open($entry['type']->phase))
            ->keyBy(fn (array $entry): string => $entry['type']->id.'|'.($entry['material']?->id ?? ''));

        $existing = $ticket->documents->keyBy(fn (TicketDocument $item): string => $item->document_type_id.'|'.($item->material_id ?? ''));

        return [
            'missing' => $expected->diffKeys($existing)->values(),
            'obsolete' => $existing->diffKeys($expected)
                ->filter(fn (TicketDocument $item): bool => $open($item->phase) && $item->document_id === null && $item->status === DocumentStatus::Missing
                    && $item->requirement !== DocumentRequirement::Optional)
                ->values(),
        ];
    }

    /**
     * Terapkan perbedaan checklist: tambahkan butir yang kurang dan lepas butir yang tidak
     * lagi diwajibkan (FR-21).
     *
     * @return array{added: list<string>, removed: list<string>, linked: int}
     */
    public function sync(Ticket $ticket, CarbonInterface $asOf): array
    {
        ['missing' => $missing, 'obsolete' => $obsolete] = $this->differences($ticket, $asOf);
        $result = $this->store($ticket, $missing->map(fn (array $entry): array => $this->item($ticket, $entry['type'], $entry['rule_id'], $entry['material'])));
        TicketDocument::query()->whereKey($obsolete->modelKeys())->delete();

        return [
            'added' => $missing->map(fn (array $entry): string => self::label($entry['type'], $entry['material']))->values()->all(),
            'removed' => $obsolete->map(fn (TicketDocument $item): string => self::label($item->documentType, $item->material))->values()->all(),
            'linked' => $result['linked'],
        ];
    }

    public static function label(DocumentType $type, ?Material $material): string
    {
        return $material === null ? $type->name : "{$type->name} · {$material->name}";
    }

    /**
     * Jenis dokumen wajib yang aktif ditambah jenis kondisional yang aturannya berlaku,
     * dipisah antara cakupan bahan baku dan cakupan lainnya.
     *
     * @return Collection<int, array{type: DocumentType, rule_id: ?int}>
     */
    private function applicableTypes(Ticket $ticket, bool $material, ?CarbonInterface $asOf = null): Collection
    {
        $ticket->loadMissing(['dosageForm', 'category']);
        $scoped = fn (Builder $query): Builder => $material
            ? $query->where('scope', DocumentScope::Material)
            : $query->where('scope', '!=', DocumentScope::Material);

        $required = $scoped(DocumentType::query())
            ->where('is_active', true)
            ->where('requirement', DocumentRequirement::Required)
            ->orderBy('id')
            ->get();

        $rules = DocumentRule::query()
            ->appliesTo($ticket->dosageForm, $ticket->category, $asOf ?? $ticket->created_at)
            ->whereHas('documentType', fn (Builder $query) => $scoped($query)
                ->where('is_active', true)
                ->where('requirement', DocumentRequirement::Conditional))
            ->with('documentType')
            ->orderBy('id')
            ->get()
            ->unique('document_type_id');

        return $required->map(fn (DocumentType $type): array => ['type' => $type, 'rule_id' => null])
            ->concat($rules->map(fn (DocumentRule $rule): array => ['type' => $rule->documentType, 'rule_id' => $rule->id]))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{items: int, linked: int}
     */
    private function store(Ticket $ticket, Collection $items): array
    {
        $ticket->documents()->createMany($items->all());

        return [
            'items' => $items->count(),
            'linked' => $items->whereNotNull('document_id')->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function item(Ticket $ticket, DocumentType $type, ?int $ruleId, ?Material $material): array
    {
        $shared = $this->validSharedDocument($ticket, $type, $material);

        return [
            'document_type_id' => $type->id,
            'material_id' => $material?->id,
            'phase' => $type->phase,
            'requirement' => $type->requirement,
            'document_rule_id' => $ruleId,
            'document_id' => $shared?->id,
            'status' => $shared === null ? DocumentStatus::Missing : DocumentStatus::UnderReview,
        ];
    }

    /**
     * Dokumen bersama milik klien tiket, milik perusahaan, atau milik bahan baku yang versi
     * terkininya lolos validasi dan, bila diperiksa ulang hari ini, belum melewati umur
     * maksimal maupun masa berlakunya. Pemakaiannya pada tiket tetap diverifikasi ulang (BR-05).
     */
    private function validSharedDocument(Ticket $ticket, DocumentType $type, ?Material $material): ?Document
    {
        if ($type->scope === DocumentScope::Ticket) {
            return null;
        }

        $document = Document::query()
            ->where('document_type_id', $type->id)
            ->where('scope', $type->scope)
            ->where('client_id', $type->scope === DocumentScope::Client ? $ticket->client_id : null)
            ->where('material_id', $material?->id)
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
