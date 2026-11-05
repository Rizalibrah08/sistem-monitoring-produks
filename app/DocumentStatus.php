<?php

namespace App;

/** Status dokumen pada checklist tiket (FR-19). */
enum DocumentStatus: string
{
    case Missing = 'belum_ada';
    case Uploaded = 'diunggah';
    case UnderReview = 'sedang_diverifikasi';
    case NeedsRevision = 'perlu_revisi';
    case Approved = 'disetujui';

    public function label(): string
    {
        return match ($this) {
            self::Missing => 'Belum ada',
            self::Uploaded => 'Diunggah',
            self::UnderReview => 'Sedang diverifikasi',
            self::NeedsRevision => 'Perlu revisi',
            self::Approved => 'Disetujui',
        };
    }
}
