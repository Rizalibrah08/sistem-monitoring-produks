<?php

namespace App\Services;

use App\Models\DocumentType;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;

class DocumentValidator
{
    public function __construct(private PdfText $pdf) {}

    /**
     * Pemeriksaan otomatis sebelum verifikasi manual (FR-71): umur dokumen terhadap batas
     * jenisnya dan masa berlaku. Format, ukuran, dan kolom wajib diperiksa saat unggah;
     * nama produk pada PDF diperiksa terpisah oleh productNameErrors().
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

    /**
     * Untuk jenis dokumen yang mewajibkannya (FR-72), nama produk tiket harus tertulis pada
     * PDF berbasis teks (FR-71). Berkas selain PDF dan PDF tanpa teks tidak dinilai.
     *
     * @return list<string>
     */
    public function productNameErrors(DocumentType $type, ?string $productName, UploadedFile $file): array
    {
        if (! $type->checks_product_name || blank($productName) || strtolower($file->getClientOriginalExtension()) !== 'pdf') {
            return [];
        }

        $text = $this->pdf->read((string) $file->getRealPath());

        return $text === null || $this->pdf->contains($text, $productName) ? [] : ["Nama produk \u{201C}{$productName}\u{201D} tidak ditemukan pada PDF."];
    }
}
