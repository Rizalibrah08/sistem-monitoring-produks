<?php

namespace App\Services;

use App\DocumentStatus;
use App\Models\Stage;
use App\Models\Ticket;

class RepairStage
{
    public const CODE = 'perbaikan_dokumen';

    /**
     * Buka tahap perbaikan dokumen saat dokumen dikembalikan (FR-26). Tahap ini berjalan
     * berdampingan dengan tahap utama tiket sehingga tahap utama tidak berubah.
     */
    public function open(Ticket $ticket): void
    {
        $stage = Stage::query()->where('code', self::CODE)->where('is_active', true)->first();

        if ($stage === null || $ticket->stageLogs()->where('stage_id', $stage->id)->whereNull('finished_at')->exists()) {
            return;
        }

        $ticket->stageLogs()->create(['stage_id' => $stage->id, 'started_at' => now()]);
    }

    /** Tutup tahap perbaikan bila tidak ada lagi dokumen tiket yang perlu revisi. */
    public function closeIfResolved(Ticket $ticket): void
    {
        if ($ticket->documents()->where('status', DocumentStatus::NeedsRevision)->exists()) {
            return;
        }

        $ticket->stageLogs()
            ->whereHas('stage', fn ($query) => $query->where('code', self::CODE))
            ->whereNull('finished_at')
            ->update(['finished_at' => now()]);
    }
}
