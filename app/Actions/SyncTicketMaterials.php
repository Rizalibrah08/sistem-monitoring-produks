<?php

namespace App\Actions;

use App\Models\Material;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ChecklistBuilder;
use Illuminate\Support\Facades\DB;

class SyncTicketMaterials
{
    public function __construct(private ChecklistBuilder $checklist, private AuditLogger $audit) {}

    /**
     * Samakan daftar bahan baku tiket dengan pilihan R&D (FR-14). Bahan baru mendapat butir
     * checklist dokumen bahan baku dan langsung tertaut ke dokumen yang masih berlaku;
     * butir milik bahan yang dilepas dihapus, sedangkan dokumen bahannya tetap tersimpan.
     *
     * @param  list<int|string>  $materialIds
     * @return array{added: int, removed: int, items: int, linked: int}
     */
    public function handle(Ticket $ticket, array $materialIds, User $actor): array
    {
        return DB::transaction(function () use ($ticket, $materialIds, $actor): array {
            Ticket::query()->whereKey($ticket->id)->lockForUpdate()->first();

            $before = $ticket->materials()->orderBy('name')->get();
            $selected = array_map('intval', $materialIds);
            $added = Material::query()->whereKey(array_diff($selected, $before->modelKeys()))->orderBy('name')->get();
            $removed = $before->reject(fn (Material $material): bool => in_array($material->id, $selected, true));

            $ticket->documents()->whereIn('material_id', $removed->modelKeys())->delete();
            $ticket->materials()->detach($removed->modelKeys());
            $ticket->materials()->attach($added->modelKeys());
            $checklist = $this->checklist->addMaterials($ticket, $added);

            if ($added->isNotEmpty() || $removed->isNotEmpty()) {
                $this->audit->record('ticket.materials_updated', $ticket,
                    ['materials' => $before->pluck('name')->all()],
                    [
                        'materials' => $ticket->materials()->orderBy('name')->pluck('name')->all(),
                        'checklist_items' => $checklist['items'],
                        'linked_material_documents' => $checklist['linked'],
                    ],
                    actor: $actor, ticketCode: $ticket->code);
            }

            return ['added' => $added->count(), 'removed' => $removed->count(), ...$checklist];
        }, attempts: 3);
    }
}
