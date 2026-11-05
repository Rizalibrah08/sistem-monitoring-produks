<?php

namespace App;

enum TicketStatus: string
{
    case Active = 'aktif';
    case Completed = 'selesai';
    case Rejected = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Completed => 'Selesai',
            self::Rejected => 'Ditolak',
        };
    }
}
