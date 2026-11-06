<?php

namespace App\Console\Commands;

use App\Services\TicketReviewFlagger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('documents:flag-expiring')]
#[Description('Menandai tiket aktif yang memakai dokumen bersama yang kedaluwarsa dalam 30 hari (FR-20)')]
class FlagExpiringSharedDocuments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TicketReviewFlagger $flagger): int
    {
        $count = $flagger->flagExpiring(now());

        $this->info("{$count} tiket ditandai perlu ditinjau.");

        return self::SUCCESS;
    }
}
