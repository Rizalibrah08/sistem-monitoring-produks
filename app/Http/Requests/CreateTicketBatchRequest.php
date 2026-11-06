<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class CreateTicketBatchRequest extends CreateTicketRequest
{
    /** Batas produk per pembuatan sekaligus. */
    public const MAX_PRODUCTS = 20;

    /**
     * Beberapa produk untuk satu klien dibuat sebagai tiket terpisah sekaligus (FR-23, BR-01).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $single = parent::rules();

        return [
            'client_id' => $single['client_id'],
            'products' => ['required', 'array', 'min:2', 'max:'.self::MAX_PRODUCTS],
            'products.*.product_name' => $single['product_name'],
            'products.*.dosage_form_id' => $single['dosage_form_id'],
            'products.*.category_id' => $single['category_id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $messages = [];

        foreach (parent::messages() as $key => $message) {
            $messages[str_starts_with($key, 'client_id') ? $key : "products.*.{$key}"] = $message;
        }

        return [
            ...$messages,
            'products.required' => 'Tambahkan produk yang akan didaftarkan.',
            'products.min' => 'Pembuatan sekaligus membutuhkan paling sedikit dua produk.',
            'products.max' => 'Paling banyak '.self::MAX_PRODUCTS.' produk sekaligus.',
        ];
    }
}
