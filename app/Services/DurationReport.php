<?php

namespace App\Services;

use App\Models\TicketStageLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DurationReport
{
    /** Menit kerja dalam satu hari kerja (08.00–16.00). */
    private const DAY_MINUTES = (WorkingDays::CLOSES_AT - WorkingDays::OPENS_AT) * 60;

    public function __construct(private WorkingDays $workingDays) {}

    /**
     * Durasi rata-rata dan persentil ke-75 per tahap dalam hari kerja untuk tahap yang
     * selesai pada periode itu, dengan waktu internal, menunggu klien, dan menunggu pihak
     * luar dipisahkan (FR-42, BR-11). Menunggu pihak luar adalah tahap yang menunggu BPOM
     * atau pihak lain; menunggu klien adalah tahap yang terlambat karena bagian klien.
     *
     * @return array{stages: list<array<string, mixed>>, totals: array{internal: float, client: float, external: float}}
     */
    public function build(CarbonInterface $from, CarbonInterface $until): array
    {
        $logs = TicketStageLog::query()
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$from->copy()->startOfDay(), $until->copy()->endOfDay()])
            ->with('stage')
            ->get()
            ->map(fn (TicketStageLog $log): array => [
                'stage' => $log->stage,
                'days' => round($this->workingDays->workingMinutesBetween($log->started_at, $log->finished_at) / self::DAY_MINUTES, 2),
                'category' => $log->stage->waits_for_external ? 'external' : ($log->delay_cause === DelayCause::CLIENT ? 'client' : 'internal'),
            ]);

        $stages = $logs->groupBy(fn (array $row): int => $row['stage']->id)
            ->map(function (Collection $rows): array {
                $stage = $rows->first()['stage'];
                $days = $rows->pluck('days')->sort()->values();

                return [
                    'stage' => $stage->name,
                    'sort_order' => $stage->sort_order,
                    'sla_days' => $stage->sla_days,
                    'count' => $days->count(),
                    'average' => round((float) $days->avg(), 2),
                    'p75' => $this->percentile($days, 75),
                    'internal' => round((float) $rows->where('category', 'internal')->sum('days'), 2),
                    'client' => round((float) $rows->where('category', 'client')->sum('days'), 2),
                    'external' => round((float) $rows->where('category', 'external')->sum('days'), 2),
                ];
            })
            ->sortBy('sort_order')->values();

        return [
            'stages' => $stages->map(fn (array $row): array => collect($row)->except('sort_order')->all())->all(),
            'totals' => [
                'internal' => round((float) $stages->sum('internal'), 2),
                'client' => round((float) $stages->sum('client'), 2),
                'external' => round((float) $stages->sum('external'), 2),
            ],
        ];
    }

    /**
     * Persentil dengan metode nearest-rank.
     *
     * @param  Collection<int, float>  $sorted
     */
    public function percentile(Collection $sorted, int $percent): float
    {
        if ($sorted->isEmpty()) {
            return 0.0;
        }

        return (float) $sorted[max(0, (int) ceil($percent / 100 * $sorted->count()) - 1)];
    }
}
