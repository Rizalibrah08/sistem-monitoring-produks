<?php

namespace App;

/** Sifat dokumen pada checklist: kondisional hanya muncul bila aturan dokumen berlaku (FR-07). */
enum DocumentRequirement: string
{
    case Required = 'wajib';
    case Conditional = 'kondisional';
    case Optional = 'opsional';

    public function label(): string
    {
        return match ($this) {
            self::Required => 'Wajib',
            self::Conditional => 'Kondisional',
            self::Optional => 'Opsional',
        };
    }
}
