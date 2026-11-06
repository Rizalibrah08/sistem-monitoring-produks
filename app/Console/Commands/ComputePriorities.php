<?php

namespace App\Console\Commands;

use App\Models\Criterion;
use App\Services\PriorityQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('priorities:compute')]
#[Description('Menghitung ulang antrean prioritas AHP-TOPSIS setiap role (FR-39, FR-40)')]
class ComputePriorities extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PriorityQueue $priorities): int
    {
        foreach (Criterion::QUEUES as $queue) {
            $count = $priorities->compute($queue, now())->count();
            $this->info("Antrean {$queue}: {$count} tiket.");
        }

        return self::SUCCESS;
    }
}
