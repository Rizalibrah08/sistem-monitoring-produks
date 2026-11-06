<?php

namespace App\Console\Commands;

use App\Services\BpomWorkflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bpom:expire-pre-registrations')]
#[Description('Menandai hasil pra-registrasi yang lewat tenggat 30 hari kerja tanpa pengajuan registrasi sebagai hangus (BR-22)')]
class ExpirePreRegistrations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BpomWorkflow $workflow): int
    {
        $count = $workflow->expirePreRegistrations(now());

        $this->info("{$count} pra-registrasi ditandai hangus.");

        return self::SUCCESS;
    }
}
