<?php

namespace App\Http\Requests;

use App\CriterionType;
use App\Models\Criterion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCriteriaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('master.manage') ?? false;
    }

    /**
     * Bobot dan jenis keempat kriteria satu antrean dikirim sekaligus.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['criteria' => ['required', 'array:'.implode(',', Criterion::CODES)]];

        foreach (Criterion::CODES as $code) {
            $rules["criteria.{$code}"] = ['required', 'array'];
            $rules["criteria.{$code}.weight"] = ['required', 'numeric', 'gt:0', 'lt:1', 'decimal:0,3'];
            $rules["criteria.{$code}.type"] = ['required', Rule::enum(CriterionType::class)];
        }

        return $rules;
    }

    /**
     * Bobot AHP satu antrean harus berjumlah 1.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $total = array_sum(array_map(fn (array $criterion): float => (float) $criterion['weight'], $this->input('criteria')));

                if (abs($total - 1) > 0.0005) {
                    $validator->errors()->add('criteria', 'Total bobot harus 1,000 (saat ini '.number_format($total, 3, ',', '.').').');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $messages = [
            'criteria.required' => 'Bobot kriteria wajib diisi.',
            'criteria.array' => 'Kriteria hanya boleh C1, C2, C3, dan C4.',
        ];

        foreach (Criterion::CODES as $code) {
            $messages += [
                "criteria.{$code}.required" => "Kriteria {$code} wajib diisi.",
                "criteria.{$code}.weight.required" => "Bobot {$code} wajib diisi.",
                "criteria.{$code}.weight.numeric" => "Bobot {$code} harus berupa angka.",
                "criteria.{$code}.weight.gt" => "Bobot {$code} harus lebih dari 0.",
                "criteria.{$code}.weight.lt" => "Bobot {$code} harus kurang dari 1.",
                "criteria.{$code}.weight.decimal" => "Bobot {$code} maksimal 3 angka di belakang koma.",
                "criteria.{$code}.type.required" => "Jenis {$code} wajib dipilih.",
                "criteria.{$code}.type.enum" => "Jenis {$code} harus benefit atau cost.",
            ];
        }

        return $messages;
    }
}
