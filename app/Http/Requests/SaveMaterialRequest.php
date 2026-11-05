<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMaterialRequest extends FormRequest
{
    /**
     * R&D boleh menambah bahan baku baru, tetapi hanya Admin yang mengubahnya (FR-04).
     */
    public function authorize(): bool
    {
        $permission = $this->route('material') === null ? 'materials.create' : 'master.manage';

        return $this->user()?->can($permission) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:150', Rule::unique(Material::class)->ignore($this->route('material'))],
            'supplier' => ['nullable', 'string', 'max:150'],
            'is_active' => [Rule::requiredIf($this->route('material') !== null), 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama bahan baku wajib diisi.',
            'name.string' => 'Nama bahan baku harus berupa teks.',
            'name.max' => 'Nama bahan baku maksimal 150 karakter.',
            'name.unique' => 'Bahan baku dengan nama ini sudah ada.',
            'supplier.string' => 'Pemasok harus berupa teks.',
            'supplier.max' => 'Pemasok maksimal 150 karakter.',
            'is_active.required' => 'Status aktif wajib diisi.',
            'is_active.boolean' => 'Status aktif tidak valid.',
        ];
    }
}
