<?php

namespace App\Services;

use App\ContractStatus;
use App\CriterionType;
use App\DocumentStatus;
use App\Models\BpomRequest;
use App\Models\BpomSubmission;
use App\Models\Criterion;
use App\Models\PriorityScore;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\TicketStageLog;
use App\StagePhase;
use App\SubmissionStatus;
use App\TicketStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PriorityQueue
{
    /** Nilai C2 bila tiket tidak memiliki tenggat BPOM yang berjalan (lebih besar dari tenggat BR-23). */
    public const NO_BPOM_DEADLINE = 60;

    public function __construct(private WorkingDays $workingDays) {}

    /**
     * Hitung ulang dan simpan skor TOPSIS serta peringkat satu antrean role (FR-38, FR-39).
     *
     * @return Collection<int, PriorityScore>
     */
    public function compute(string $queue, CarbonInterface $now): Collection
    {
        $criteria = Criterion::query()->where('queue', $queue)->get()->keyBy('code');
        $rows = $this->eligible($queue)->map(fn (Ticket $ticket): array => ['ticket_id' => $ticket->id, ...$this->criteria($ticket, $now)])->values();
        $scores = $this->topsis(
            $rows->map(fn (array $row): array => [$row['c1'], $row['c2'], $row['c3'], $row['c4']])->all(),
            array_map(fn (string $code): float => (float) ($criteria[$code]->weight ?? 0), Criterion::CODES),
            array_map(fn (string $code): CriterionType => $criteria[$code]->type ?? CriterionType::Benefit, Criterion::CODES),
        );

        $ranked = $rows->map(fn (array $row, int $index): array => [...$row, 'score' => $scores[$index]])
            ->sortBy([['score', 'desc'], ['c2', 'asc'], ['ticket_id', 'asc']])->values()
            ->map(fn (array $row, int $index): array => [...$row, 'queue' => $queue, 'rank' => $index + 1, 'computed_at' => $now]);

        DB::transaction(function () use ($queue, $ranked): void {
            PriorityScore::query()->where('queue', $queue)->delete();
            PriorityScore::query()->insert($ranked->all());
        });

        return PriorityScore::query()->where('queue', $queue)->orderBy('rank')->get();
    }

    /**
     * Skor TOPSIS: normalisasi vektor, pembobotan, lalu kedekatan relatif terhadap solusi
     * ideal positif. Benefit makin besar makin baik; cost sebaliknya. Kolom bernilai nol
     * semua tidak membedakan alternatif.
     *
     * @param  list<list<float>>  $matrix
     * @param  list<float>  $weights
     * @param  list<CriterionType>  $types
     * @return list<float>
     */
    public function topsis(array $matrix, array $weights, array $types): array
    {
        if ($matrix === []) {
            return [];
        }

        $columns = count($weights);
        $norms = [];

        for ($j = 0; $j < $columns; $j++) {
            $norms[$j] = sqrt(array_sum(array_map(fn (array $row): float => $row[$j] ** 2, $matrix)));
        }

        $weighted = array_map(fn (array $row): array => array_map(
            fn (int $j): float => $norms[$j] > 0 ? $row[$j] / $norms[$j] * $weights[$j] : 0.0,
            range(0, $columns - 1),
        ), $matrix);

        $best = [];
        $worst = [];

        for ($j = 0; $j < $columns; $j++) {
            $column = array_column($weighted, $j);
            $best[$j] = $types[$j] === CriterionType::Benefit ? max($column) : min($column);
            $worst[$j] = $types[$j] === CriterionType::Benefit ? min($column) : max($column);
        }

        return array_map(function (array $row) use ($best, $worst, $columns): float {
            $toBest = sqrt(array_sum(array_map(fn (int $j): float => ($row[$j] - $best[$j]) ** 2, range(0, $columns - 1))));
            $toWorst = sqrt(array_sum(array_map(fn (int $j): float => ($row[$j] - $worst[$j]) ** 2, range(0, $columns - 1))));

            return $toBest + $toWorst > 0 ? round($toWorst / ($toBest + $toWorst), 6) : 1.0;
        }, $weighted);
    }

    /**
     * Nilai kriteria satu tiket (FR-38): C1 critical ratio tahap berjalan, C2 sisa hari
     * kerja menuju tenggat BPOM terdekat, C3 persentase dokumen fase berjalan yang disetujui,
     * dan C4 umur tiket dalam hari kerja.
     *
     * @return array{c1: float, c2: float, c3: float, c4: float}
     */
    public function criteria(Ticket $ticket, CarbonInterface $now): array
    {
        return [
            'c1' => $this->criticalRatio($ticket, $now),
            'c2' => (float) $this->bpomDaysLeft($ticket, $now),
            'c3' => $this->completeness($ticket),
            'c4' => (float) $this->workingDays->daysUntil($ticket->created_at, $now),
        ];
    }

    /**
     * Tiket aktif yang tidak sedang menunggu BPOM atau pihak luar. Antrean RA juga tidak
     * memuat tiket yang kontraknya belum aktif (BR-09); antrean R&D dan QA/QC hanya memuat
     * tiket yang memiliki tugas dokumen bagi role itu.
     *
     * @return Collection<int, Ticket>
     */
    public function eligible(string $queue): Collection
    {
        return Ticket::query()
            ->where('status', TicketStatus::Active)
            ->where(fn ($query) => $query->whereNull('current_stage_id')->orWhereHas('currentStage', fn ($query) => $query->where('waits_for_external', false)))
            ->when($queue === 'ra', fn ($query) => $query->whereHas('contract', fn ($query) => $query->where('status', ContractStatus::Active)))
            ->with(['stageLogs.stage', 'documents.documentType', 'bpomSubmissions.requests', 'client'])
            ->get()
            ->filter(fn (Ticket $ticket): bool => ! in_array($queue, ['rnd', 'qa'], true) || $this->hasWorkFor($ticket, $queue))
            ->values();
    }

    private function hasWorkFor(Ticket $ticket, string $role): bool
    {
        return $ticket->documents->contains(fn (TicketDocument $item): bool => $role === 'qa' && $item->documentType->verifier_role === 'qa' && $item->status === DocumentStatus::UnderReview
            || $item->documentType->pic_role === $role && in_array($item->status, [DocumentStatus::Missing, DocumentStatus::NeedsRevision], true));
    }

    private function criticalRatio(Ticket $ticket, CarbonInterface $now): float
    {
        $log = $ticket->stageLogs
            ->filter(fn (TicketStageLog $log): bool => $log->finished_at === null && $log->due_at !== null && $log->stage_id === $ticket->current_stage_id)
            ->first();

        if ($log === null || ! $log->stage->sla_days) {
            return 1.0;
        }

        $remaining = $log->due_at->gte($now)
            ? $this->workingDays->workingMinutesBetween($now, $log->due_at)
            : -$this->workingDays->workingMinutesBetween($log->due_at, $now);

        return round($remaining / ($log->stage->sla_days * (WorkingDays::CLOSES_AT - WorkingDays::OPENS_AT) * 60), 4);
    }

    private function bpomDaysLeft(Ticket $ticket, CarbonInterface $now): int
    {
        $deadlines = $ticket->bpomSubmissions
            ->flatMap(fn (BpomSubmission $submission): Collection => $submission->requests->whereNull('responded_at')->map(fn (BpomRequest $request) => $request->deadline))
            ->merge($ticket->bpomSubmissions
                ->filter(fn (BpomSubmission $submission): bool => $submission->phase === StagePhase::PreRegistration && $submission->status === SubmissionStatus::ResultIssued
                    && ! $ticket->bpomSubmissions->contains(fn (BpomSubmission $other): bool => $other->phase === StagePhase::Registration && $other->status !== SubmissionStatus::Expired))
                ->map(fn (BpomSubmission $submission) => $submission->registration_deadline))
            ->filter();

        if ($deadlines->isEmpty()) {
            return self::NO_BPOM_DEADLINE;
        }

        $nearest = $deadlines->min();

        return $nearest->lt($now->copy()->startOfDay()) ? 0 : $this->workingDays->daysUntil($now, $nearest);
    }

    private function completeness(Ticket $ticket): float
    {
        $phase = $ticket->phase === StagePhase::Initial ? StagePhase::PreRegistration : $ticket->phase;
        $items = $ticket->documents->filter(fn (TicketDocument $item): bool => $item->belongsToPhase($phase));

        return $items->isEmpty() ? 0.0 : round($items->where('status', DocumentStatus::Approved)->count() / $items->count() * 100, 2);
    }
}
