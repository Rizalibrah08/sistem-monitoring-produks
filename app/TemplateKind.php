<?php

namespace App;

/** Jenis templat Word yang dapat diisi otomatis (FR-67). */
enum TemplateKind: string
{
    case Contract = 'kontrak';
    case PowerOfAttorney = 'surat_kuasa';
    case Statement = 'surat_pernyataan';
    case Invoice = 'tagihan';
    case Other = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Kontrak',
            self::PowerOfAttorney => 'Surat kuasa',
            self::Statement => 'Surat pernyataan',
            self::Invoice => 'Tagihan',
            self::Other => 'Lainnya',
        };
    }
}
