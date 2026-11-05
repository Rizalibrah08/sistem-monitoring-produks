<?php

namespace App\Services;

use InvalidArgumentException;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;

class TemplateInspector
{
    /**
     * Penanda ${...} di badan, header, dan footer dokumen Word, termasuk penanda
     * yang terpecah beberapa format teks, diurutkan tanpa duplikat.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException bila berkas bukan dokumen Word yang valid
     */
    public function placeholders(string $path): array
    {
        try {
            $variables = (new TemplateProcessor($path))->getVariables();
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Berkas bukan dokumen Word yang valid.', previous: $exception);
        }

        $variables = array_values(array_unique($variables));
        sort($variables);

        return $variables;
    }

    /**
     * Penanda yang tidak ada di daftar variabel yang diizinkan.
     *
     * @param  list<string>  $placeholders
     * @return list<string>
     */
    public function unknown(array $placeholders): array
    {
        return array_values(array_diff($placeholders, array_keys(config('document_templates.variables'))));
    }
}
