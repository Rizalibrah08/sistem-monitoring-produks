<?php

namespace App\Http\Requests;

use App\Models\Holiday;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveHolidayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('master.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['bail', 'required', 'date_format:Y-m-d', Rule::unique(Holiday::class)->ignore($this->route('holiday'))],
            'name' => ['required', 'string', 'max:150'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date.required' => 'Tanggal wajib diisi.',
            'date.date_format' => 'Format tanggal tidak valid.',
            'date.unique' => 'Tanggal ini sudah terdaftar sebagai hari libur.',
            'name.required' => 'Keterangan wajib diisi.',
            'name.string' => 'Keterangan harus berupa teks.',
            'name.max' => 'Keterangan maksimal 150 karakter.',
        ];
    }
}
