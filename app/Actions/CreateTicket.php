<?php

namespace App\Actions;

use App\Models\Stage;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ChecklistBuilder;
use App\StagePhase;
use App\TicketStatus;
use Illuminate\Support\Facades\DB;

class CreateTicket
{
    public function __construct(private ChecklistBuilder $checklist, private AuditLogger $audit) {}

    /**
     * Buat tiket berkode unik PRD-TAHUN-NOMOR di fase awal, catat tahap pertama,
     * susun checklist dokumen kedua fase, dan tautkan dokumen bersama
     * yang masih berlaku (FR-10, FR-11, FR-12, FR-34).
     *
     * @param  array{client_id: int|string, product_name: string, dosage_form_id: int|string, category_id: int|string}  $data
     */
    public function handle(array $data, User $actor): Ticket
    {
        return DB::transaction(function () use ($data, $actor): Ticket {
            $firstStage = Stage::query()->where('code', 'pengembangan_sampel')->first()
                ?? Stage::query()->where('is_active', true)->orderBy('sort_order')->first();

            $ticket = Ticket::query()->create([
                ...$data,
                'code' => $this->nextCode(),
                'phase' => StagePhase::Initial,
                'current_stage_id' => $firstStage?->id,
                'status' => TicketStatus::Active,
                'created_by' => $actor->id,
            ]);

            if ($firstStage !== null) {
                $ticket->stageLogs()->create(['stage_id' => $firstStage->id, 'started_at' => $ticket->created_at]);
            }

            $checklist = $this->checklist->build($ticket->load(['dosageForm', 'category']));

            $this->audit->record('ticket.created', $ticket, after: [
                'client_id' => $ticket->client_id,
                'product_name' => $ticket->product_name,
                'dosage_form_id' => $ticket->dosage_form_id,
                'category_id' => $ticket->category_id,
                'checklist_items' => $checklist['items'],
                'linked_shared_documents' => $checklist['linked'],
            ], actor: $actor, ticketCode: $ticket->code);

            return $ticket;
        }, attempts: 5);
    }

    /** Nomor urut per tahun; tiket terakhir dikunci agar dua pembuatan bersamaan tidak bertabrakan. */
    private function nextCode(): string
    {
        $prefix = 'PRD-'.now()->year.'-';
        $last = Ticket::query()->where('code', 'like', $prefix.'%')->orderByDesc('code')->lockForUpdate()->value('code');
        $number = $last === null ? 1 : (int) substr($last, strlen($prefix)) + 1;

        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
