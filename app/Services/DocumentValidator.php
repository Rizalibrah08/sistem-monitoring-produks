<?php

namespace App\Services;

use App\Models\DocumentType;
use Carbon\CarbonInterface;

class DocumentValidator
{
    /**
     * Pemeriksaan otomatis sebelum verifikasi manual (FR-71): umur dokumen terhadap batas
     * jenisnya dan masa berlaku. Format, ukuran, dan kolom wajib diperiksa saat unggah.
     *
     * @return list<string> alasan kegagalan; kosong berarti lolos
     */
    public function errors(DocumentType $type, ?CarbonInterface $issuedAt, ?CarbonInterface $expiresAt, CarbonInterface $today): array
    {
        $errors = [];
        $today = $today->copy()->startOfDay();

        if ($type->max_age_months !== null && $issuedAt !== null && $issuedAt->copy()->addMonthsNoOverflow($type->max_age_months)->lt($today)) {
            $errors[] = "Umur dokumen melebihi {$type->max_age_months} bulan sejak terbit ({$issuedAt->format('d/m/Y')}).";
        }

        if ($expiresAt !== null && $expiresAt->lt($today)) {
            $errors[] = "Dokumen sudah kedaluwarsa pada {$expiresAt->format('d/m/Y')}.";
        }

        return $errors;
    }
}
