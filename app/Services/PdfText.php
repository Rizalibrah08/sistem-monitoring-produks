<?php

namespace App\Services;

use Smalot\PdfParser\Parser;
use Throwable;

class PdfText
{
    /**
     * Teks yang dapat dibaca dari PDF berbasis teks; null bila berkas tidak dapat dibaca atau
     * tidak berisi teks, misalnya hasil pindaian, karena OCR tidak dipakai (FR-71).
     */
    public function read(string $path): ?string
    {
        try {
            $text = (new Parser)->parseFile($path)->getText();
        } catch (Throwable) {
            return null;
        }

        return trim($text) === '' ? null : $text;
    }

    /**
     * Apakah nama produk muncul di teks, tanpa membedakan huruf besar kecil, spasi, atau tanda baca.
     */
    public function contains(string $text, string $phrase): bool
    {
        $needle = $this->normalize($phrase);

        return $needle !== '' && str_contains($this->normalize($text), $needle);
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value)));
    }
}
