<?php

namespace App;

/** Pemicu penanda "perlu ditinjau" pada tiket aktif (FR-20). */
enum ReviewTrigger: string
{
    case NewVersion = 'versi_baru';
    case ExpiringSoon = 'mendekati_kedaluwarsa';

    public function label(): string
    {
        return match ($this) {
            self::NewVersion => 'Versi baru dokumen bersama',
            self::ExpiringSoon => 'Dokumen bersama mendekati kedaluwarsa',
        };
    }
}
