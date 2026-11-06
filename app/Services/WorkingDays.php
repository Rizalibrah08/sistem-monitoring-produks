<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class WorkingDays
{
    /** Jam kerja harian menurut BR-08 (WIB). */
    public const OPENS_AT = 8;

    public const CLOSES_AT = 16;

    /**
     * Tanggal setelah sejumlah hari kerja dari tanggal awal (BR-08): Senin–Jumat tanpa hari
     * libur nasional dan cuti bersama dari master data. Tanggal awal tidak ikut dihitung.
     */
    public function add(CarbonInterface $start, int $days): CarbonImmutable
    {
        $date = CarbonImmutable::parse($start->toDateString());
        $holidays = $this->holidays($date, $date->addDays($days * 2 + 30));

        while ($days > 0) {
            $date = $date->addDay();

            if ($this->isWorkingDay($date, $holidays)) {
                $days--;
            }
        }

        return $date;
    }

    /**
     * Tenggat tahap dengan SLA sejumlah hari kerja, dihitung sebagai jam kerja 08.00–16.00
     * WIB (FR-35, BR-08). Mulai di luar jam kerja dihitung dari jam buka berikutnya.
     */
    public function dueAt(CarbonInterface $start, int $days): CarbonImmutable
    {
        $timezone = config('app.timezone');
        $cursor = CarbonImmutable::parse($start)->setTimezone($timezone);
        $remaining = $days * (self::CLOSES_AT - self::OPENS_AT) * 60;
        $holidays = $this->holidays($cursor->subDay(), $cursor->addDays($days * 2 + 30));

        while (true) {
            $opens = $cursor->setTime(self::OPENS_AT, 0);
            $closes = $cursor->setTime(self::CLOSES_AT, 0);

            if (! $this->isWorkingDay($cursor, $holidays) || $cursor->gte($closes)) {
                $cursor = $cursor->addDay()->setTime(self::OPENS_AT, 0);

                continue;
            }

            $cursor = $cursor->max($opens);
            $available = (int) $cursor->diffInMinutes($closes);

            if ($remaining <= $available) {
                return $cursor->addMinutes($remaining);
            }

            $remaining -= $available;
            $cursor = $cursor->addDay()->setTime(self::OPENS_AT, 0);
        }
    }

    /**
     * Lama jam kerja di antara dua waktu, untuk durasi tahap (FR-35, FR-42).
     */
    public function workingMinutesBetween(CarbonInterface $from, CarbonInterface $until): int
    {
        $timezone = config('app.timezone');
        $start = CarbonImmutable::parse($from)->setTimezone($timezone);
        $end = CarbonImmutable::parse($until)->setTimezone($timezone);

        if ($end->lte($start)) {
            return 0;
        }

        $holidays = $this->holidays($start->subDay(), $end->addDay());
        $minutes = 0;

        for ($day = $start->startOfDay(); $day->lte($end); $day = $day->addDay()) {
            if (! $this->isWorkingDay($day, $holidays)) {
                continue;
            }

            $windowStart = $day->setTime(self::OPENS_AT, 0)->max($start);
            $windowEnd = $day->setTime(self::CLOSES_AT, 0)->min($end);

            if ($windowEnd->gt($windowStart)) {
                $minutes += (int) $windowStart->diffInMinutes($windowEnd);
            }
        }

        return $minutes;
    }

    /** @param  Collection<string, int>  $holidays */
    private function isWorkingDay(CarbonInterface $date, Collection $holidays): bool
    {
        return ! $date->isWeekend() && ! $holidays->has($date->toDateString());
    }

    /** @return Collection<string, int> */
    private function holidays(CarbonInterface $from, CarbonInterface $until): Collection
    {
        return Holiday::query()
            ->where('date', '>', $from->toDateString())
            ->where('date', '<=', $until->toDateString())
            ->pluck('date')
            ->map(fn (mixed $value): string => substr((string) $value, 0, 10))
            ->flip();
    }
}
