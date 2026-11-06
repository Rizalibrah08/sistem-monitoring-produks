<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class WorkingDays
{
    /**
     * Tanggal setelah sejumlah hari kerja dari tanggal awal (BR-08): Senin–Jumat tanpa hari
     * libur nasional dan cuti bersama dari master data. Tanggal awal tidak ikut dihitung.
     */
    public function add(CarbonInterface $start, int $days): CarbonImmutable
    {
        $date = CarbonImmutable::parse($start->toDateString());
        $holidays = Holiday::query()
            ->where('date', '>', $date->toDateString())
            ->where('date', '<=', $date->addDays($days * 2 + 30)->toDateString())
            ->pluck('date')
            ->map(fn (mixed $value): string => substr((string) $value, 0, 10))
            ->flip();

        while ($days > 0) {
            $date = $date->addDay();

            if (! $date->isWeekend() && ! $holidays->has($date->toDateString())) {
                $days--;
            }
        }

        return $date;
    }
}
