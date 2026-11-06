<?php

namespace App\Console\Commands;

use App\Models\TicketStageLog;
use App\Services\AuditLogger;
use App\Services\DelayCause;
use App\Services\StaffNotifier;
use App\TicketStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sla:check')]
#[Description('Menandai tahap tiket aktif yang melewati tenggat SLA beserta penyebabnya (FR-37)')]
class CheckStageDeadlines extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DelayCause $cause, AuditLogger $audit, StaffNotifier $notifier): int
    {
        $logs = TicketStageLog::query()
            ->whereNull('finished_at')->whereNull('overdue_at')
            ->whereNotNull('due_at')->where('due_at', '<', now())
            ->whereHas('ticket', fn ($query) => $query->where('status', TicketStatus::Active))
            ->with(['ticket.contract', 'ticket.client.marketingUser', 'stage'])
            ->get();

        foreach ($logs as $log) {
            $log->update(['overdue_at' => now(), 'delay_cause' => $cause->of($log)]);
            $audit->record('stage.overdue', $log, after: [
                'stage' => $log->stage->name,
                'due_at' => $log->due_at->toIso8601String(),
                'cause' => $log->delay_cause,
            ], source: 'system', ticketCode: $log->ticket->code);
            $notifier->stageOverdue($log);
        }

        $this->info("{$logs->count()} tahap ditandai terlambat.");

        return self::SUCCESS;
    }
}
