<?php

namespace App\Http\Requests;

use App\Models\TicketDocument;
use App\ReviewDecision;
use App\ReviewLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewTicketDocumentRequest extends FormRequest
{
    /**
     * QA/QC memverifikasi isi, RA menyetujui pemakaian, pada tiket yang dapat dilihatnya.
     */
    public function authorize(): bool
    {
        $item = $this->route('ticketDocument');
        $level = ReviewLevel::tryFrom((string) $this->route('level'));

        return $item instanceof TicketDocument && $level !== null
            && ($this->user()?->can($level->permission()) ?? false)
            && $item->ticket->isVisibleTo($this->user());
    }

    /**
     * Alasan wajib diisi saat dokumen dikembalikan (FR-24, FR-25).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
            'note' => [Rule::requiredIf($this->input('decision') === ReviewDecision::NeedsRevision->value), 'nullable', 'string', 'max:2000'],
            'token' => ['required', 'string', 'size:64'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan wajib dipilih.',
            'decision.enum' => 'Keputusan harus disetujui atau perlu revisi.',
            'note.required' => 'Alasan revisi wajib diisi.',
            'note.max' => 'Alasan maksimal 2000 karakter.',
            'token.required' => 'Muat ulang halaman lalu periksa kembali.',
            'token.size' => 'Muat ulang halaman lalu periksa kembali.',
        ];
    }
}
