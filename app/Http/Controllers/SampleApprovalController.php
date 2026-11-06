<?php

namespace App\Http\Controllers;

use App\Actions\RecordSampleDecision;
use App\Http\Requests\RecordSampleDecisionRequest;
use App\Models\SampleApproval;
use App\Models\Ticket;
use App\SampleDecision;
use App\StagePhase;
use App\TicketStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SampleApprovalController extends Controller
{
    /**
     * Tiket aktif di fase awal beserta keputusan sampel terakhirnya (FR-13).
     */
    public function index(Request $request): Response
    {
        $tickets = Ticket::query()->visibleTo($request->user())
            ->where('status', TicketStatus::Active)
            ->where('phase', StagePhase::Initial)
            ->with(['client', 'currentStage', 'sampleApprovals' => fn ($query) => $query->orderByDesc('revision')])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString()
            ->through(function (Ticket $ticket): array {
                /** @var SampleApproval|null $latest */
                $latest = $ticket->sampleApprovals->first();

                return [
                    'id' => $ticket->id,
                    'code' => $ticket->code,
                    'product_name' => $ticket->product_name,
                    'client' => $ticket->client->name,
                    'current_stage' => $ticket->currentStage?->name,
                    'revisions' => $ticket->sampleApprovals->count(),
                    'decision' => $latest?->decision->value,
                    'decision_label' => $latest?->decision->label() ?? 'Menunggu keputusan',
                    'decided_at' => $latest?->created_at?->format('d/m/Y'),
                    'show_url' => route('tickets.show', $ticket, absolute: false),
                ];
            });

        return Inertia::render('Backend/Samples', ['tickets' => $tickets]);
    }

    public function store(RecordSampleDecisionRequest $request, Ticket $ticket, RecordSampleDecision $record): RedirectResponse
    {
        $approval = $record->handle($ticket, $request->validated(), $request->user());

        return back()->with('status', $approval->decision === SampleDecision::Approved
            ? "Sampel revisi {$approval->revision} disetujui klien."
            : "Revisi sampel ke-{$approval->revision} dicatat.");
    }
}
