<?php

namespace App\Actions;

use App\Models\User;
use App\Services\AuditLogger;
use App\StaffRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SaveStaffUser
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array{name: string, email: string, role: string, is_active: bool|int|string, password?: ?string, unavailable_from?: ?string, unavailable_until?: ?string} $data */
    public function handle(array $data, User $actor, ?User $subject = null): User
    {
        return DB::transaction(function () use ($data, $actor, $subject): User {
            Role::query()->where('name', StaffRole::Admin->value)->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
            $currentActor = User::query()->with('roles')->findOrFail($actor->id);
            abort_unless($currentActor->is_active, 403);
            Gate::forUser($currentActor)->authorize('users.manage');

            $user = $subject === null ? new User : User::query()->with('roles')->lockForUpdate()->findOrFail($subject->id);

            if ($subject !== null && $user->is($currentActor)) {
                if (! (bool) $data['is_active']) {
                    throw ValidationException::withMessages(['is_active' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
                }

                if ($data['role'] !== StaffRole::Admin->value) {
                    throw ValidationException::withMessages(['role' => 'Anda tidak dapat mengubah role akun sendiri.']);
                }
            }

            if (User::query()->where('email', $data['email'])->when($user->exists, fn ($query) => $query->whereKeyNot($user->id))->exists()) {
                throw ValidationException::withMessages(['email' => 'Email sudah digunakan.']);
            }

            $before = $user->exists ? $this->snapshot($user) : [];
            $user->fill(['name' => $data['name'], 'email' => $data['email']]);
            $user->forceFill([
                'is_active' => (bool) $data['is_active'],
                'unavailable_from' => $data['unavailable_from'] ?? null,
                'unavailable_until' => $data['unavailable_until'] ?? null,
            ]);

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
            }

            $user->save();
            $user->syncRoles($data['role']);
            $user->load('roles');
            $after = $this->snapshot($user);

            if ($subject !== null && filled($data['password'] ?? null)) {
                $after['credentials_changed'] = true;
            }

            $this->audit->record($subject === null ? 'user.created' : 'user.updated', $user, $before, $after, actor: $currentActor);

            return $user;
        }, attempts: 5);
    }

    /** @return array{name: string, email: string, role: ?string, is_active: bool, unavailable_from: ?string, unavailable_until: ?string} */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'is_active' => $user->is_active,
            'unavailable_from' => $user->unavailable_from?->format('Y-m-d'),
            'unavailable_until' => $user->unavailable_until?->format('Y-m-d'),
        ];
    }
}
