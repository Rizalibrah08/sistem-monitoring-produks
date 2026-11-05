<?php

namespace App\Http\Requests;

use App\Models\DocumentTemplate;
use App\Services\TemplateInspector;
use App\TemplateKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class UploadTemplateRequest extends FormRequest
{
    /** @var list<string> */
    private array $placeholders = [];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('master.manage') ?? false;
    }

    /**
     * Templat baru membutuhkan nama dan jenis; versi baru cukup berkas dan catatan.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'file' => ['bail', 'required', 'file', 'extensions:docx', 'max:20480'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->route('documentTemplate') === null) {
            $rules['name'] = ['required', 'string', 'max:150', Rule::unique(DocumentTemplate::class)];
            $rules['kind'] = ['required', Rule::enum(TemplateKind::class)];
        }

        return $rules;
    }

    /**
     * Setiap penanda isian harus terdaftar sebagai variabel yang diizinkan (FR-67).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(TemplateInspector $inspector): array
    {
        return [
            function (Validator $validator) use ($inspector): void {
                $file = $this->file('file');

                if ($validator->errors()->has('file') || ! $file instanceof UploadedFile) {
                    return;
                }

                try {
                    $this->placeholders = $inspector->placeholders($file->getRealPath());
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('file', 'Berkas bukan dokumen Word (.docx) yang valid.');

                    return;
                }

                $unknown = $inspector->unknown($this->placeholders);

                if ($unknown !== []) {
                    $names = implode(', ', array_map(fn (string $name): string => '${'.$name.'}', $unknown));
                    $validator->errors()->add('file', "Penanda tidak dikenal: {$names}. Gunakan penanda dari daftar variabel yang diizinkan.");
                }
            },
        ];
    }

    /** @return list<string> */
    public function placeholders(): array
    {
        return $this->placeholders;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Berkas templat wajib diunggah.',
            'file.file' => 'Berkas templat gagal diunggah.',
            'file.extensions' => 'Templat harus berupa berkas Word (.docx).',
            'file.max' => 'Ukuran templat maksimal 20 MB.',
            'notes.string' => 'Catatan harus berupa teks.',
            'notes.max' => 'Catatan maksimal 255 karakter.',
            'name.required' => 'Nama templat wajib diisi.',
            'name.string' => 'Nama templat harus berupa teks.',
            'name.max' => 'Nama templat maksimal 150 karakter.',
            'name.unique' => 'Templat dengan nama ini sudah ada. Unggah sebagai versi baru.',
            'kind.required' => 'Jenis templat wajib dipilih.',
            'kind.enum' => 'Jenis templat tidak valid.',
        ];
    }
}
