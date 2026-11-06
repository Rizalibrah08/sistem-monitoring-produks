<?php

namespace App\Http\Requests;

use App\DocumentRequirement;
use App\DocumentScope;
use App\Models\DocumentType;
use App\StagePhase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDocumentTypeRequest extends FormRequest
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
            'name' => ['bail', 'required', 'string', 'max:150', Rule::unique(DocumentType::class)->ignore($this->route('documentType'))],
            'phase' => ['required', Rule::enum(StagePhase::class)->except(StagePhase::Initial)],
            'scope' => ['required', Rule::enum(DocumentScope::class)],
            'requirement' => ['required', Rule::enum(DocumentRequirement::class)],
            'pic_role' => ['required', Rule::in(DocumentType::PIC_ROLES)],
            'verifier_role' => ['nullable', Rule::in(DocumentType::VERIFIER_ROLES)],
            'requires_expiry_date' => ['required', 'boolean'],
            'max_age_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'checks_product_name' => ['sometimes', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama jenis dokumen wajib diisi.',
            'name.string' => 'Nama jenis dokumen harus berupa teks.',
            'name.max' => 'Nama jenis dokumen maksimal 150 karakter.',
            'name.unique' => 'Jenis dokumen dengan nama ini sudah ada.',
            'phase.required' => 'Fase wajib dipilih.',
            'phase.enum' => 'Fase dokumen harus pra-registrasi, registrasi, atau kedua fase.',
            'scope.required' => 'Cakupan wajib dipilih.',
            'scope.enum' => 'Cakupan tidak valid.',
            'requirement.required' => 'Sifat dokumen wajib dipilih.',
            'requirement.enum' => 'Sifat dokumen tidak valid.',
            'pic_role.required' => 'PIC wajib dipilih.',
            'pic_role.in' => 'PIC harus Marketing, R&D, QA/QC, Regulatory Affairs, atau klien.',
            'verifier_role.in' => 'Verifikator isi harus QA/QC atau Regulatory Affairs.',
            'requires_expiry_date.required' => 'Pilih apakah dokumen memiliki masa berlaku.',
            'requires_expiry_date.boolean' => 'Pilihan masa berlaku tidak valid.',
            'max_age_months.integer' => 'Umur maksimal harus berupa jumlah bulan.',
            'max_age_months.min' => 'Umur maksimal minimal 1 bulan.',
            'max_age_months.max' => 'Umur maksimal paling lama 120 bulan.',
            'is_active.required' => 'Status aktif wajib diisi.',
            'is_active.boolean' => 'Status aktif tidak valid.',
        ];
    }
}
