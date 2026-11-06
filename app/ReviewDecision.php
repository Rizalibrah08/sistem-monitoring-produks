<?php

namespace App;

/** Keputusan verifikasi manual dokumen (FR-24, FR-25). */
enum ReviewDecision: string
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
