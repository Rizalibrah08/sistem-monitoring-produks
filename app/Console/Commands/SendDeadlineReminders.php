<?php

namespace App\Console\Commands;

use App\Models\BpomRequest;
use App\Models\BpomSubmission;
use App\Models\TicketStageLog;
use App\Services\StaffNotifier;
use App\Services\WorkingDays;
use App\StagePhase;
use App\SubmissionStatus;
use App\TicketStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('notifications:deadlines')]
#[Description('Mengirim pengingat tenggat tahap dan tenggat BPOM ke pusat notifikasi (FR-36, FR-89)')]
class SendDeadlineReminders extends Command
{
    /** @var list<int> sisa hari kerja menuju tenggat tambahan data BPOM yang diingatkan */
    private const BPOM_REQUEST_DAYS = [3, 1];

    /** @var list<int> sisa hari kerja menuju tenggat pengajuan registrasi yang diingatkan */
    private const REGISTRATION_DAYS = [10, 5, 1];

    /**
     * Execute the console command.
     */
    public function handle(StaffNotifier $notifier, WorkingDays $workingDays): int
    {
        $sent = $this->stageReminders($notifier, $workingDays)
            + $this->bpomRequestReminders($notifier, $workingDays)
            + $this->registrationReminders($notifier, $workingDays);

        $this->info("{$sent} pengingat dikirim.");

        return self::SUCCESS;
    }

    /** Satu hari kerja (delapan jam kerja) sebelum tenggat tahap. */
    private function stageReminders(StaffNotifier $notifier, WorkingDays $workingDays): int
    {
        $logs = TicketStageLog::query()
            ->whereNull('finished_at')->whereNull('reminded_at')->whereNull('overdue_at')
            ->whereNotNull('due_at')->where('due_at', '>', now())
            ->whereHas('ticket', fn (Builder $query) => $query->where('status', TicketStatus::Active))
            ->with(['ticket.client.marketingUser', 'stage'])
            ->get()
            ->filter(fn (TicketStageLog $log): bool => $workingDays->workingMinutesBetween(now(), $log->due_at) <= (WorkingDays::CLOSES_AT - WorkingDays::OPENS_AT) * 60);

        foreach ($logs as $log) {
            $notifier->stageDueSoon($log);
            $log->update(['reminded_at' => now()]);
        }

        return $logs->count();
    }

    private function bpomRequestReminders(StaffNotifier $notifier, WorkingDays $workingDays): int
    {
        $count = 0;
        $requests = BpomRequest::query()->whereNull('responded_at')->whereDate('deadline', '>=', now()->toDateString())
            ->whereHas('submission.ticket', fn (Builder $query) => $query->where('status', TicketStatus::Active))
            ->with('submission.ticket')->get();

        foreach ($requests as $request) {
            $left = $workingDays->daysUntil(now(), $request->deadline);

            if (in_array($left, self::BPOM_REQUEST_DAYS, true) && $request->reminded_days !== $left) {
                $notifier->bpomDeadline($request->submission->ticket, "Tambahan data {$request->code()}", $request->deadline->format('d/m/Y'), $left, $request->sequence === BpomRequest::MAX_SEQUENCE);
                $request->update(['reminded_days' => $left]);
                $count++;
            }
        }

        return $count;
    }

    private function registrationReminders(StaffNotifier $notifier, WorkingDays $workingDays): int
    {
        $count = 0;
        $submissions = BpomSubmission::query()
            ->where('phase', StagePhase::PreRegistration)->where('status', SubmissionStatus::ResultIssued)
            ->whereDate('registration_deadline', '>=', now()->toDateString())
            ->whereHas('ticket', fn (Builder $query) => $query->where('status', TicketStatus::Active))
            ->whereDoesntHave('ticket.bpomSubmissions', fn (Builder $query) => $query->where('phase', StagePhase::Registration)->where('status', '!=', SubmissionStatus::Expired))
            ->with('ticket')->get();

        foreach ($submissions as $submission) {
            $left = $workingDays->daysUntil(now(), $submission->registration_deadline);

            if (in_array($left, self::REGISTRATION_DAYS, true) && $submission->reminded_days !== $left) {
                $notifier->bpomDeadline($submission->ticket, 'Pengajuan registrasi', $submission->registration_deadline->format('d/m/Y'), $left);
                $submission->update(['reminded_days' => $left]);
                $count++;
            }
        }

        return $count;
    }
}
