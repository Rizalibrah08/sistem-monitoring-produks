<?php

namespace App\Http\Requests;

use App\Models\DocumentType;
use App\Models\Ticket;
use App\Models\TicketDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentVersionRequest extends FormRequest
{
    /**
     * Pengunggah adalah staf dengan role PIC jenis dokumen, Marketing atas nama klien,
     * atau Regulatory Affairs yang menghimpun berkas, pada tiket yang dapat dilihatnya.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $ticket = $this->route('ticket');
        $item = $this->route('ticketDocument');

        return $ticket instanceof Ticket
            && $item instanceof TicketDocument
            && $user?->can('tickets.view')
            && $ticket->isVisibleTo($user)
            && $item->canBeUploadedBy($user);
    }

    /**
     * Format dan ukuran sesuai FR-16; tanggal terbit dan kedaluwarsa wajib bila jenis
     * dokumennya memiliki umur maksimal atau masa berlaku (FR-17).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->documentType();

        return [
            'file' => ['bail', 'required', 'file', 'extensions:pdf,jpg,jpeg,png,docx,xlsx', 'mimes:pdf,jpg,jpeg,png,docx,xlsx', 'max:20480'],
            'number' => ['required', 'string', 'max:100'],
            'issued_at' => [Rule::requiredIf($type?->max_age_months !== null), 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'expires_at' => [Rule::requiredIf((bool) $type?->requires_expiry_date), 'nullable', 'date_format:Y-m-d', 'after_or_equal:issued_at'],
        ];
    }

    /** Jenis dokumen yang menentukan kolom tanggal wajib. */
    protected function documentType(): ?DocumentType
    {
        return $this->route('ticketDocument')?->documentType;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih berkas yang akan diunggah.',
            'file.file' => 'Berkas gagal diunggah.',
            'file.extensions' => 'Format berkas harus PDF, JPG, PNG, DOCX, atau XLSX.',
            'file.mimes' => 'Format berkas harus PDF, JPG, PNG, DOCX, atau XLSX.',
            'file.max' => 'Ukuran berkas maksimal 20 MB.',
            'number.required' => 'Nomor dokumen wajib diisi.',
            'number.max' => 'Nomor dokumen maksimal 100 karakter.',
            'issued_at.required' => 'Tanggal terbit wajib diisi untuk dokumen dengan umur maksimal.',
            'issued_at.date_format' => 'Format tanggal terbit tidak valid.',
            'issued_at.before_or_equal' => 'Tanggal terbit tidak boleh setelah hari ini.',
            'expires_at.required' => 'Tanggal kedaluwarsa wajib diisi untuk dokumen dengan masa berlaku.',
            'expires_at.date_format' => 'Format tanggal kedaluwarsa tidak valid.',
            'expires_at.after_or_equal' => 'Tanggal kedaluwarsa tidak boleh sebelum tanggal terbit.',
        ];
    }
}
