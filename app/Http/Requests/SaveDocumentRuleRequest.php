<?php

namespace App\Http\Requests;

use App\DocumentRequirement;
use App\Models\Category;
use App\Models\DocumentRule;
use App\Models\DocumentType;
use App\Models\DosageForm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class SaveDocumentRuleRequest extends FormRequest
{
    /**
     * Admin dan Regulatory Affairs mengelola aturan dokumen kondisional (FR-07).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('document_rules.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type_id' => [
                'bail', 'required', 'integer',
                Rule::exists(DocumentType::class, 'id')->where('requirement', DocumentRequirement::Conditional->value)->where('is_active', true),
                $this->sameRuleIsNotRegistered(),
            ],
            'dosage_form_id' => ['nullable', 'integer', Rule::exists(DosageForm::class, 'id'), 'required_without:category_id'],
            'category_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_active' => [Rule::requiredIf($this->route('documentRule') !== null), 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'document_type_id.required' => 'Jenis dokumen wajib dipilih.',
            'document_type_id.integer' => 'Jenis dokumen tidak valid.',
            'document_type_id.exists' => 'Aturan hanya untuk jenis dokumen kondisional yang aktif.',
            'document_type_id.unique' => 'Aturan yang sama sudah terdaftar.',
            'dosage_form_id.required_without' => 'Pilih sediaan, kategori, atau keduanya.',
            'dosage_form_id.exists' => 'Sediaan tidak ditemukan.',
            'category_id.exists' => 'Kategori tidak ditemukan.',
            'effective_from.required' => 'Tanggal mulai berlaku wajib diisi.',
            'effective_from.date_format' => 'Format tanggal mulai berlaku tidak valid.',
            'notes.string' => 'Catatan harus berupa teks.',
            'notes.max' => 'Catatan maksimal 255 karakter.',
            'is_active.required' => 'Status aktif wajib diisi.',
            'is_active.boolean' => 'Status aktif tidak valid.',
        ];
    }

    /** Kombinasi jenis dokumen, sediaan, kategori, dan tanggal berlaku harus unik. */
    private function sameRuleIsNotRegistered(): Unique
    {
        return Rule::unique(DocumentRule::class, 'document_type_id')
            ->where(function (Builder $query): void {
                $query->where('effective_from', $this->input('effective_from'));

                foreach (['dosage_form_id', 'category_id'] as $column) {
                    filled($this->input($column)) ? $query->where($column, $this->input($column)) : $query->whereNull($column);
                }
            })
            ->ignore($this->route('documentRule'));
    }
}
