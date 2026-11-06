<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use App\SampleDecision;
use App\TicketStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordSampleDecisionRequest extends FormRequest
{
    /**
     * Marketing mencatat keputusan sampel untuk tiket aktif kliennya (FR-13).
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $ticket = $this->route('ticket');

        return $ticket instanceof Ticket
            && ($user?->can('samples.record') ?? false)
            && $ticket->isVisibleTo($user)
            && $ticket->status === TicketStatus::Active;
    }

    /**
     * Catatan wajib saat klien meminta revisi agar masukan klien tidak hilang.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(SampleDecision::class)],
            'note' => [Rule::requiredIf($this->input('decision') === SampleDecision::NeedsRevision->value), 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan klien wajib dipilih.',
            'decision.enum' => 'Keputusan harus disetujui atau perlu revisi.',
            'note.required' => 'Catatan revisi wajib diisi.',
            'note.string' => 'Catatan harus berupa teks.',
            'note.max' => 'Catatan maksimal 2000 karakter.',
        ];
    }
}
