<?php

namespace App;

/** Tingkat verifikasi manual dokumen (F06): isi oleh QA/QC, pemakaian pada tiket oleh RA. */
enum ReviewLevel: string
{
    case Content = 'isi';
    case Usage = 'pemakaian';

    public function label(): string
    {
        return match ($this) {
            self::Content => 'Verifikasi isi (QA/QC)',
            self::Usage => 'Persetujuan pemakaian (RA)',
        };
    }

    public function permission(): string
    {
        return match ($this) {
            self::Content => 'documents.verify_content',
            self::Usage => 'documents.approve_usage',
        };
    }
}
