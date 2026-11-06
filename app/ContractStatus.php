<?php

namespace App;

/** Status kontrak (MoU) per tiket (FR-74). */
enum ContractStatus: string
{
    case Draft = 'draf';
    case Sent = 'dikirim';
    case Signed = 'ditandatangani';
    case Active = 'aktif';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Sent => 'Dikirim',
            self::Signed => 'Ditandatangani',
            self::Active => 'Aktif',
        };
    }
}
