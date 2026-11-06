<?php

namespace App\Http\Requests;

use App\Models\Contract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadSignedContractRequest extends FormRequest
{
    /**
     * Marketing dapat mengunggah kontrak bertanda tangan atas nama klien (FR-74). Unggahan
     * klien lewat tautan memakai subkelas yang memeriksa token di controller.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $contract = $this->route('contract');

        return $contract instanceof Contract && ($user?->can('contracts.manage') ?? false) && $contract->ticket->isVisibleTo($user);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['bail', 'required', 'file', 'extensions:pdf,jpg,jpeg,png', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih berkas kontrak bertanda tangan.',
            'file.file' => 'Berkas gagal diunggah.',
            'file.extensions' => 'Kontrak bertanda tangan harus berupa PDF, JPG, atau PNG.',
            'file.mimes' => 'Kontrak bertanda tangan harus berupa PDF, JPG, atau PNG.',
            'file.max' => 'Ukuran berkas maksimal 20 MB.',
        ];
    }
}
