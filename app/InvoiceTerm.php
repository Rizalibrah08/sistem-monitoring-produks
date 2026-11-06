<?php

namespace App;

/** Termin tagihan kontrak (BR-15): DP saat kontrak aktif, pelunasan saat NIE terbit. */
enum InvoiceTerm: string
{
    case DownPayment = 'dp';
    case Settlement = 'pelunasan';

    public function label(): string
    {
        return match ($this) {
            self::DownPayment => 'DP',
            self::Settlement => 'Pelunasan',
        };
    }
}
