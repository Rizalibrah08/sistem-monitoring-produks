<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Client;
use App\Models\DosageForm;
use App\StaffRole;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTicketRequest extends FormRequest
{
    /**
     * Marketing membuat tiket pendaftaran produk (FR-10).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('tickets.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['bail', 'required', 'integer', Rule::exists(Client::class, 'id'), $this->handledByThisMarketingStaff()],
            'product_name' => ['required', 'string', 'max:150'],
            'dosage_form_id' => ['bail', 'required', 'integer', Rule::exists(DosageForm::class, 'id')->where('is_active', true)],
            'category_id' => ['bail', 'required', 'integer', Rule::exists(Category::class, 'id')->where('is_active', true)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'client_id.required' => 'Klien wajib dipilih.',
            'client_id.exists' => 'Klien tidak ditemukan.',
            'product_name.required' => 'Nama produk wajib diisi.',
            'product_name.max' => 'Nama produk maksimal 150 karakter.',
            'dosage_form_id.required' => 'Sediaan wajib dipilih.',
            'dosage_form_id.exists' => 'Sediaan harus dipilih dari daftar sediaan aktif.',
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists' => 'Kategori harus dipilih dari daftar kategori aktif.',
        ];
    }

    /** Staf Marketing hanya membuat tiket untuk klien yang ditanganinya, agar tiket tetap terlihat olehnya. */
    private function handledByThisMarketingStaff(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $user = $this->user();

            if ($user->hasRole(StaffRole::Marketing->value) && Client::query()->whereKey($value)->value('marketing_user_id') !== $user->id) {
                $fail('Pilih klien yang Anda tangani sebagai PIC Marketing.');
            }
        };
    }
}
