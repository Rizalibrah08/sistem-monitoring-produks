<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use App\StagePhase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateUploadTokenRequest extends FormRequest
{
    /**
     * Marketing dan RA membuat tautan unggah untuk tiket yang dapat dilihatnya (FR-18).
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket && ($user?->can('upload_links.manage') ?? false) && $ticket->isVisibleTo($user);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phase' => ['required', Rule::enum(StagePhase::class)->only([StagePhase::PreRegistration, StagePhase::Registration])],
            'days' => ['required', 'integer', 'min:1', 'max:'.config('upload_links.max_days')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $max = config('upload_links.max_days');

        return [
            'phase.required' => 'Fase wajib dipilih.',
            'phase.enum' => 'Fase harus pra-registrasi atau registrasi.',
            'days.required' => 'Masa berlaku wajib diisi.',
            'days.integer' => 'Masa berlaku harus berupa angka hari.',
            'days.min' => 'Masa berlaku minimal 1 hari.',
            'days.max' => "Masa berlaku maksimal {$max} hari.",
        ];
    }
}
