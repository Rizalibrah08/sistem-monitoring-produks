<?php

namespace App\Http\Requests;

use App\Models\Contract;
use App\Models\DocumentTemplate;
use App\Models\Ticket;
use App\TemplateKind;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContractRequest extends FormRequest
{
    /**
     * Marketing membuat dan mengubah kontrak untuk tiket kliennya (FR-73).
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $contract = $this->route('contract');

        return ($user?->can('contracts.manage') ?? false)
            && (! $contract instanceof Contract || $contract->ticket->isVisibleTo($user));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ticket_id' => [Rule::requiredIf($this->route('contract') === null), 'bail', 'integer', Rule::exists(Ticket::class, 'id'), $this->visibleTicket()],
            'document_template_id' => ['bail', 'required', 'integer', Rule::exists(DocumentTemplate::class, 'id')->where('kind', TemplateKind::Contract->value)->where('is_active', true), $this->hasVersion()],
            'service_value' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'dp_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'payment_due_days' => ['required', 'integer', 'min:1', 'max:90'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'ticket_id.required' => 'Tiket wajib dipilih.',
            'ticket_id.exists' => 'Tiket tidak ditemukan.',
            'document_template_id.required' => 'Templat kontrak wajib dipilih.',
            'document_template_id.exists' => 'Pilih templat kontrak yang aktif.',
            'service_value.required' => 'Nilai jasa wajib diisi.',
            'service_value.integer' => 'Nilai jasa harus berupa angka rupiah.',
            'service_value.min' => 'Nilai jasa minimal Rp1.',
            'service_value.max' => 'Nilai jasa terlalu besar.',
            'dp_percent.required' => 'Persentase DP wajib diisi.',
            'dp_percent.min' => 'Persentase DP minimal 1%.',
            'dp_percent.max' => 'Persentase DP maksimal 99% agar ada pelunasan.',
            'payment_due_days.required' => 'Jatuh tempo pembayaran wajib diisi.',
            'payment_due_days.min' => 'Jatuh tempo minimal 1 hari.',
            'payment_due_days.max' => 'Jatuh tempo maksimal 90 hari.',
        ];
    }

    private function visibleTicket(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $ticket = Ticket::query()->with('client')->find($value);

            if ($ticket !== null && ! $ticket->isVisibleTo($this->user())) {
                $fail('Pilih tiket klien yang Anda tangani.');
            }
        };
    }

    private function hasVersion(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! DocumentTemplate::query()->whereKey($value)->whereHas('versions')->exists()) {
                $fail('Templat kontrak belum memiliki berkas.');
            }
        };
    }
}
