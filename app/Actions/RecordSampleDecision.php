<?php

namespace App\Actions;

use App\Models\SampleApproval;
use App\Models\Stage;
use App\Models\Ticket;
use App\Models\User;
use App\SampleDecision;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordSampleDecision
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Catat keputusan klien atas sampel dengan nomor revisi berikutnya (FR-13, US-02).
     * Setelah sampel disetujui tidak ada keputusan baru, dan tiket yang masih di tahap
     * pengembangan sampel berpindah ke tahap kontrak (MoU).
     *
     * @param  array{decision: string, note?: ?string}  $data
     */
    public function handle(Ticket $ticket, array $data, User $actor): SampleApproval
    {
        return DB::transaction(function () use ($ticket, $data, $actor): SampleApproval {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if ($ticket->sampleApprovals()->where('decision', SampleDecision::Approved)->exists()) {
                throw ValidationException::withMessages(['decision' => 'Sampel tiket ini sudah disetujui.']);
            }

            $decision = SampleDecision::from($data['decision']);
            $approval = $ticket->sampleApprovals()->create([
                'revision' => (int) $ticket->sampleApprovals()->max('revision') + 1,
                'decision' => $decision,
                'note' => filled($data['note'] ?? null) ? $data['note'] : null,
                'recorded_by' => $actor->id,
            ]);

            $nextStage = $decision === SampleDecision::Approved ? $this->moveToContractStage($ticket) : null;

            $this->audit->record('sample.recorded', $approval, after: [
                'revision' => $approval->revision,
                'decision' => $decision->value,
                'note' => $approval->note,
                'next_stage' => $nextStage?->name,
            ], actor: $actor, ticketCode: $ticket->code);

            return $approval;
        }, attempts: 3);
    }

    private function moveToContractStage(Ticket $ticket): ?Stage
    {
        $ticket->loadMissing('currentStage');
        $contract = Stage::query()->where('code', 'kontrak')->where('is_active', true)->first();

        if ($ticket->currentStage?->code !== 'pengembangan_sampel' || $contract === null) {
            return null;
        }

        $ticket->stageLogs()->where('stage_id', $ticket->current_stage_id)->whereNull('finished_at')->update(['finished_at' => now()]);
        $ticket->stageLogs()->create(['stage_id' => $contract->id, 'started_at' => now()]);
        $ticket->update(['current_stage_id' => $contract->id]);

        return $contract;
    }
}
