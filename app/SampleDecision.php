<?php

namespace App;

/** Keputusan klien atas sampel yang dicatat Marketing (FR-13). */
enum SampleDecision: string
{
    case Approved = 'disetujui';
    case NeedsRevision = 'perlu_revisi';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Disetujui',
            self::NeedsRevision => 'Perlu revisi',
        };
    }
}
