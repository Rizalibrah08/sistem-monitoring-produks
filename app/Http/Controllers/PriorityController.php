<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\PriorityScore;
use App\Services\PriorityQueue;
use App\StaffRole;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PriorityController extends Controller
{
    /**
     * Antrean prioritas role pengguna, dihitung ulang saat halaman dibuka (FR-40). Manajer
     * dapat melihat antrean setiap role; Marketing hanya melihat tiket kliennya.
     */
    public function __invoke(Request $request, PriorityQueue $priorities): Response
    {
        $user = $request->user();
        $role = $user->getRoleNames()->first();
        $isManager = $role === StaffRole::Manager->value;
        $filters = $request->validate(['queue' => ['nullable', Rule::in(Criterion::QUEUES)]]);
        $queue = $isManager ? ($filters['queue'] ?? 'ra') : $role;
        abort_unless(in_array($queue, Criterion::QUEUES, true), 403);

        $rank = 0;
        $rows = $priorities->compute($queue, now())
            ->load(['ticket.client', 'ticket.currentStage'])
            ->filter(fn (PriorityScore $score): bool => $score->ticket->isVisibleTo($user))
            ->map(function (PriorityScore $score) use (&$rank): array {
                $ticket = $score->ticket;

                return [
                    'rank' => ++$rank,
                    'ticket_id' => $ticket->id,
                    'code' => $ticket->code,
                    'product_name' => $ticket->product_name,
                    'client' => $ticket->client->name,
                    'phase_label' => $ticket->phase->label(),
                    'current_stage' => $ticket->currentStage?->name,
                    'c1' => $score->c1,
                    'c2' => $score->c2,
                    'c3' => $score->c3,
                    'c4' => $score->c4,
                    'score' => $score->score,
                    'reason' => $this->reason($score),
                    'ticket_url' => route('tickets.show', $ticket, absolute: false),
                ];
            })->values();

        return Inertia::render('Backend/Priorities', [
            'queue' => $queue,
            'queue_label' => Criterion::queueLabel($queue),
            'queues' => $isManager ? array_map(fn (string $value): array => ['value' => $value, 'label' => Criterion::queueLabel($value)], Criterion::QUEUES) : null,
            'criteria' => Criterion::query()->where('queue', $queue)->orderBy('code')->get()
                ->map(fn (Criterion $criterion): array => ['code' => $criterion->code, 'name' => $criterion->name, 'weight' => $criterion->weight, 'type' => $criterion->type->label()]),
            'rows' => $rows,
            'computed_at' => now()->format('d/m/Y H:i'),
        ]);
    }

    /** Alasan utama urutan yang mudah dibaca (FR-86). */
    private function reason(PriorityScore $score): string
    {
        return match (true) {
            $score->c1 < 0 => 'Tahap berjalan sudah melewati tenggat.',
            $score->c2 <= 5 => 'Tenggat BPOM tinggal '.(int) $score->c2.' hari kerja.',
            $score->c1 <= 0.25 => 'Tenggat tahap berjalan sudah dekat.',
            default => 'Umur tiket '.(int) $score->c4.' hari kerja; kelengkapan dokumen '.(int) $score->c3.'%.',
        };
    }
}
