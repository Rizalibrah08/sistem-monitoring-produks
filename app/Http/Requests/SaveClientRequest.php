<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Models\User;
use App\StaffRole;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveClientRequest extends FormRequest
{
    /**
     * Marketing dan Manajer (pengganti PIC yang cuti, BR-12) mengelola data klien (FR-66).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('clients.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'pic_name' => ['required', 'string', 'max:150'],
            'whatsapp' => ['bail', 'required', 'string', 'regex:/^62[0-9]{8,13}$/', Rule::unique(Client::class)->ignore($this->route('client'))],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'marketing_user_id' => ['bail', 'required', 'integer', $this->activeMarketingStaff()],
        ];
    }

    /**
     * Nomor WhatsApp dinormalkan sebelum divalidasi, dan PIC Marketing bawaan adalah
     * staf Marketing yang sedang mengisi formulir.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('whatsapp'))) {
            $this->merge(['whatsapp' => Client::normalizeWhatsapp($this->input('whatsapp'))]);
        }

        if (blank($this->input('marketing_user_id')) && $this->user()?->hasRole(StaffRole::Marketing->value)) {
            $this->merge(['marketing_user_id' => $this->user()->id]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama klien wajib diisi.',
            'name.max' => 'Nama klien maksimal 150 karakter.',
            'company.max' => 'Nama perusahaan maksimal 150 karakter.',
            'pic_name.required' => 'Nama PIC klien wajib diisi.',
            'pic_name.max' => 'Nama PIC klien maksimal 150 karakter.',
            'whatsapp.required' => 'Nomor WhatsApp wajib diisi.',
            'whatsapp.regex' => 'Nomor WhatsApp harus nomor Indonesia, misalnya 0812xxxxxxxx.',
            'whatsapp.unique' => 'Nomor WhatsApp ini sudah dipakai klien lain.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'marketing_user_id.required' => 'Pilih PIC Marketing untuk klien ini.',
            'marketing_user_id.integer' => 'PIC Marketing tidak valid.',
        ];
    }

    private function activeMarketingStaff(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $isActiveMarketing = User::query()->role(StaffRole::Marketing->value)->where('is_active', true)->whereKey($value)->exists();

            if (! $isActiveMarketing) {
                $fail('PIC Marketing harus staf Marketing yang aktif.');
            }
        };
    }
}
