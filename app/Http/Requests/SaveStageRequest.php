<?php

namespace App\Http\Requests;

use App\Models\Stage;
use App\StagePhase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('master.manage') ?? false;
    }

    /**
     * Tahap internal wajib memiliki SLA; tahap yang menunggu BPOM atau pihak luar
     * tidak memakai SLA karena perhitungannya berhenti (FR-05, FR-35).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:100', Rule::unique(Stage::class)->ignore($this->route('stage'))],
            'phase' => ['required', Rule::enum(StagePhase::class)],
            'sort_order' => ['required', 'integer', 'min:1', 'max:999'],
            'waits_for_external' => ['required', 'boolean'],
            'sla_days' => ['nullable', 'integer', 'min:1', 'max:365', 'required_if_declined:waits_for_external', 'prohibited_if_accepted:waits_for_external'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama tahap wajib diisi.',
            'name.string' => 'Nama tahap harus berupa teks.',
            'name.max' => 'Nama tahap maksimal 100 karakter.',
            'name.unique' => 'Tahap dengan nama ini sudah ada.',
            'phase.required' => 'Fase wajib dipilih.',
            'phase.enum' => 'Fase tidak valid.',
            'sort_order.required' => 'Urutan wajib diisi.',
            'sort_order.integer' => 'Urutan harus berupa angka.',
            'sort_order.min' => 'Urutan minimal 1.',
            'sort_order.max' => 'Urutan maksimal 999.',
            'waits_for_external.required' => 'Jenis tahap wajib dipilih.',
            'waits_for_external.boolean' => 'Jenis tahap tidak valid.',
            'sla_days.integer' => 'SLA harus berupa jumlah hari kerja.',
            'sla_days.min' => 'SLA minimal 1 hari kerja.',
            'sla_days.max' => 'SLA maksimal 365 hari kerja.',
            'sla_days.required_if_declined' => 'SLA wajib diisi untuk tahap internal.',
            'sla_days.prohibited_if_accepted' => 'Tahap yang menunggu pihak luar tidak memakai SLA.',
            'is_active.required' => 'Status aktif wajib diisi.',
            'is_active.boolean' => 'Status aktif tidak valid.',
        ];
    }
}
