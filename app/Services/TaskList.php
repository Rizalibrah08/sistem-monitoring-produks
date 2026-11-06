<?php

namespace App\Services;

use App\ContractStatus;
use App\DocumentStatus;
use App\Models\BpomRequest;
use App\Models\Contract;
use App\Models\DocumentType;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\User;
use App\SampleDecision;
use App\StaffRole;
use App\StagePhase;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TaskList
{
    /**
     * Tugas staf sesuai role-nya (FR-43): dokumen yang harus diunggah atau direvisi,
     * dokumen yang harus diverifikasi, tambahan data BPOM yang harus ditanggapi, serta
     * kontrak dan sampel yang menunggu Marketing.
     *
     * @return Collection<int, array{kind: string, title: string, detail: string, ticket_code: string, due: ?string, url: string}>
     */
    public function for(User $user): Collection
    {
        $role = $user->getRoleNames()->first();
        $tickets = Ticket::query()->visibleTo($user)->where('status', TicketStatus::Active);

        $tasks = $this->documentsToPrepare($tickets, $role);

        if ($role === StaffRole::Quality->value) {
            $tasks = $tasks->merge($this->reviews($tickets, 'qa'));
        }

        if ($role === StaffRole::Regulatory->value) {
            $tasks = $tasks->merge($this->reviews($tickets, 'ra'))->merge($this->bpomRequests($tickets));
        }

        if ($role === StaffRole::Marketing->value) {
            $tasks = $tasks->merge($this->marketingTasks($tickets));
        }

        return $tasks->sortBy(fn (array $task): string => $task['due_sort'] ?? '9999')->values()
            ->map(fn (array $task): array => collect($task)->except('due_sort')->all());
    }

    /** @return Collection<int, array<string, ?string>> */
    private function documentsToPrepare(Builder $tickets, ?string $role): Collection
    {
        $pics = $role === StaffRole::Marketing->value ? [DocumentType::CLIENT_PIC, StaffRole::Marketing->value] : [$role];

        return TicketDocument::query()
            ->whereIn('status', [DocumentStatus::Missing, DocumentStatus::NeedsRevision])
            ->whereIn('ticket_id', (clone $tickets)->select('id'))
            ->whereHas('documentType', fn (Builder $query) => $query->whereIn('pic_role', $pics))
            ->with(['ticket', 'documentType', 'material'])
            ->get()->toBase()
            ->filter(fn (TicketDocument $item): bool => $item->ticket->phase === StagePhase::Initial || $item->belongsToPhase($item->ticket->phase))
            ->map(fn (TicketDocument $item): array => [
                'kind' => $item->status === DocumentStatus::NeedsRevision ? 'revisi' : 'unggah',
                'title' => ($item->status === DocumentStatus::NeedsRevision ? 'Revisi ' : 'Unggah ').$item->documentType->name.($item->material ? " · {$item->material->name}" : ''),
                'detail' => $item->revision_reason ?? $item->ticket->product_name,
                'ticket_code' => $item->ticket->code,
                'due' => null,
                'url' => route('tickets.show', $item->ticket_id, absolute: false),
            ]);
    }

    /** @return Collection<int, array<string, ?string>> */
    private function reviews(Builder $tickets, string $role): Collection
    {
        return TicketDocument::query()
            ->where('status', DocumentStatus::UnderReview)
            ->whereIn('ticket_id', (clone $tickets)->select('id'))
            ->whereHas('document.currentVersion', fn (Builder $query) => $query->where('validation_passed', true))
            ->with(['ticket', 'documentType', 'document'])
            ->get()->toBase()
            ->filter(fn (TicketDocument $item): bool => $role === 'qa'
                ? $item->needsContentReview() && ! $item->contentApproved()
                : ! $item->needsContentReview() || $item->contentApproved())
            ->map(fn (TicketDocument $item): array => [
                'kind' => 'verifikasi',
                'title' => ($role === 'qa' ? 'Verifikasi isi ' : 'Setujui pemakaian ').$item->documentType->name,
                'detail' => $item->ticket->product_name,
                'ticket_code' => $item->ticket->code,
                'due' => null,
                'url' => route($role === 'qa' ? 'quality.index' : 'verification.index', absolute: false),
            ]);
    }

    /** @return Collection<int, array<string, ?string>> */
    private function bpomRequests(Builder $tickets): Collection
    {
        return BpomRequest::query()->whereNull('responded_at')->whereIn('ticket_id', (clone $tickets)->select('id'))
            ->with('submission.ticket')->get()->toBase()
            ->map(fn (BpomRequest $request): array => [
                'kind' => 'bpom',
                'title' => "Tanggapi tambahan data {$request->code()}",
                'detail' => $request->note,
                'ticket_code' => $request->submission->ticket->code,
                'due' => $request->deadline->format('d/m/Y'),
                'due_sort' => $request->deadline->toDateString(),
                'url' => route('tickets.show', $request->ticket_id, absolute: false),
            ]);
    }

    /** @return Collection<int, array<string, ?string>> */
    private function marketingTasks(Builder $tickets): Collection
    {
        $contracts = Contract::query()->where('status', ContractStatus::Signed)->whereIn('ticket_id', (clone $tickets)->select('id'))->with('ticket')->get()->toBase()
            ->map(fn (Contract $contract): array => [
                'kind' => 'kontrak',
                'title' => "Konfirmasi kontrak {$contract->number}",
                'detail' => 'Klien sudah mengunggah kontrak bertanda tangan.',
                'ticket_code' => $contract->ticket->code,
                'due' => null,
                'url' => route('tickets.show', $contract->ticket_id, absolute: false),
            ]);

        $samples = (clone $tickets)->where('phase', StagePhase::Initial)
            ->whereDoesntHave('sampleApprovals', fn (Builder $query) => $query->where('decision', SampleDecision::Approved))
            ->get()->toBase()
            ->map(fn (Ticket $ticket): array => [
                'kind' => 'sampel',
                'title' => 'Catat keputusan sampel',
                'detail' => $ticket->product_name,
                'ticket_code' => $ticket->code,
                'due' => null,
                'url' => route('tickets.show', $ticket, absolute: false),
            ]);

        return $contracts->merge($samples);
    }
}
