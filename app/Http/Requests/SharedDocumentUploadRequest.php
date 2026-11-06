<?php

namespace App\Http\Requests;

use App\DocumentScope;
use App\Models\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class SharedDocumentUploadRequest extends UploadDocumentVersionRequest
{
    /**
     * QA/QC dan RA mengelola dokumen bersama perusahaan (F05).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('shared_documents.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type_id' => ['bail', 'required', 'integer', Rule::exists(DocumentType::class, 'id')->where('scope', DocumentScope::Company->value)->where('is_active', true)],
            ...parent::rules(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'document_type_id.required' => 'Jenis dokumen wajib dipilih.',
            'document_type_id.exists' => 'Pilih jenis dokumen bersama perusahaan yang aktif.',
        ];
    }

    protected function documentType(): ?DocumentType
    {
        return DocumentType::query()->find($this->input('document_type_id'));
    }
}
