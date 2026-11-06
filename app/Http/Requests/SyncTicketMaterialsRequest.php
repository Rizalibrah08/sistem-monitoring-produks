<?php

namespace App\Http\Requests;

use App\Models\Material;
use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncTicketMaterialsRequest extends FormRequest
{
    /**
     * R&D memilih daftar bahan baku tiket (FR-14).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('ticket_materials.manage') ?? false;
    }

    /**
     * Bahan baru harus aktif; bahan yang sudah terpilih tetap boleh dipertahankan
     * walaupun kemudian dinonaktifkan di master.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->route('ticket');
        $selected = $ticket->materials()->pluck('materials.id')->all();

        return [
            'material_ids' => ['present', 'array', 'max:100'],
            'material_ids.*' => ['bail', 'integer', 'distinct', Rule::exists(Material::class, 'id')->where(
                fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $selected),
            )],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'material_ids.present' => 'Daftar bahan baku wajib dikirim.',
            'material_ids.array' => 'Daftar bahan baku tidak valid.',
            'material_ids.max' => 'Bahan baku maksimal 100 per tiket.',
            'material_ids.*.integer' => 'Bahan baku tidak valid.',
            'material_ids.*.distinct' => 'Bahan baku tidak boleh dipilih dua kali.',
            'material_ids.*.exists' => 'Bahan baku harus dipilih dari daftar bahan aktif.',
        ];
    }
}
