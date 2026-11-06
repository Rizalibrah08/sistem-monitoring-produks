<?php

namespace App;

/** Status pengajuan ke BPOM per fase (F08). */
enum SubmissionStatus: string
{
    case AwaitingResult = 'menunggu_hasil';
    case ResultIssued = 'hasil_terbit';
    case Expired = 'hangus';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingResult => 'Menunggu hasil BPOM',
            self::ResultIssued => 'Hasil terbit',
            self::Expired => 'Hangus',
        };
    }
}
