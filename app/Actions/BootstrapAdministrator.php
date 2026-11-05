<?php

namespace App\Actions;

use App\Models\User;
use App\Rules\BcryptPassword;
use App\Services\AuditLogger;
use App\StaffRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class BootstrapAdministrator
{
    public function __construct(private RolesAndPermissionsSeeder $roles, private AuditLogger $audit) {}

    public function handle(string $name, string $email, #[\SensitiveParameter] ?string $password): User
    {
        $data = Validator::make(['name' => trim($name), 'email' => mb_strtolower(trim($email)), 'password' => $password], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['bail', 'required', 'string', new BcryptPassword, Password::min(12)->letters()->numbers()],
        ])->validate();

        return DB::transaction(function () use ($data): User {
            $this->roles->run();
            Role::query()->where('name', StaffRole::Admin->value)->where('guard_name', 'web')->lockForUpdate()->firstOrFail();

            if (User::query()->where('is_active', true)->role(StaffRole::Admin)->exists()) {
                throw ValidationException::withMessages(['email' => 'Admin awal sudah tersedia. Tambahkan admin berikutnya melalui aplikasi.']);
            }

            if (User::query()->where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'Email sudah digunakan. Akun yang ada tidak akan ditimpa.']);
            }

            $user = User::query()->create($data);
            $user->assignRole(StaffRole::Admin);
            $this->audit->record('user.bootstrap_admin', $user, after: ['name' => $user->name, 'email' => $user->email, 'role' => StaffRole::Admin->value, 'is_active' => true], source: 'system');

            return $user;
        }, attempts: 5);
    }
}
