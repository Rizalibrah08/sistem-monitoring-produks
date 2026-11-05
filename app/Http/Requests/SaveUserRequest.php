<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\BcryptPassword;
use App\StaffRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($this->route('user'))],
            'role' => ['required', Rule::enum(StaffRole::class)],
            'is_active' => ['required', 'boolean'],
            'password' => ['bail', Rule::requiredIf($this->route('user') === null), 'nullable', 'string', new BcryptPassword, Password::min(12)->letters()->numbers(), 'confirmed'],
            'unavailable_from' => ['nullable', 'required_with:unavailable_until', 'date_format:Y-m-d'],
            'unavailable_until' => ['nullable', 'required_with:unavailable_from', 'date_format:Y-m-d', 'after_or_equal:unavailable_from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.string' => 'Nama harus berupa teks.',
            'name.max' => 'Nama maksimal 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',
            'role.required' => 'Role wajib dipilih.',
            'role.enum' => 'Role tidak valid.',
            'is_active.required' => 'Status akun wajib diisi.',
            'is_active.boolean' => 'Status akun tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 12 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'unavailable_from.required_with' => 'Tanggal mulai cuti wajib diisi.',
            'unavailable_until.required_with' => 'Tanggal akhir cuti wajib diisi.',
            'unavailable_from.date_format' => 'Format tanggal mulai cuti tidak valid.',
            'unavailable_until.date_format' => 'Format tanggal akhir cuti tidak valid.',
            'unavailable_until.after_or_equal' => 'Tanggal akhir cuti tidak boleh sebelum tanggal mulai.',
        ];
    }
}
