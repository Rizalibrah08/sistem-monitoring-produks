<?php

namespace App;

/** Pemilik dokumen menurut BR-03: dokumen bersama dipakai ulang lintas tiket. */
enum DocumentScope: string
{
    case Ticket = 'tiket';
    case Client = 'klien';
    case Material = 'bahan_baku';
    case Company = 'perusahaan';

    public function label(): string
    {
        return match ($this) {
            self::Ticket => 'Tiket',
            self::Client => 'Klien',
            self::Material => 'Bahan baku',
            self::Company => 'Perusahaan',
        };
    }
}
