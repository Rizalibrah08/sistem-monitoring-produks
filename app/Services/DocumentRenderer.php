<?php

namespace App\Services;

use App\Models\TemplateVersion;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

class DocumentRenderer
{
    /**
     * Isi penanda ${...} pada versi templat Word dengan nilai yang diberikan (FR-69).
     * Penanda tanpa nilai dikosongkan agar tidak tercetak mentah di dokumen.
     *
     * @param  array<string, string>  $values
     * @return string isi berkas .docx
     */
    public function fill(TemplateVersion $version, array $values): string
    {
        $source = tempnam(sys_get_temp_dir(), 'tpl');
        $target = tempnam(sys_get_temp_dir(), 'doc');

        try {
            file_put_contents($source, Storage::disk('local')->get($version->path));
            $processor = new TemplateProcessor($source);

            foreach ($processor->getVariables() as $variable) {
                $processor->setValue($variable, $values[$variable] ?? '');
            }

            $processor->saveAs($target);

            return (string) file_get_contents($target);
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    /**
     * Ubah dokumen Word menjadi PDF melalui Gotenberg (LibreOffice).
     *
     * @throws RequestException bila Gotenberg menolak atau tidak dapat dihubungi
     */
    public function toPdf(string $docx, string $filename): string
    {
        return Http::timeout(90)
            ->attach('files', $docx, $filename)
            ->post(rtrim((string) config('services.gotenberg.url'), '/').'/forms/libreoffice/convert')
            ->throw()
            ->body();
    }
}
