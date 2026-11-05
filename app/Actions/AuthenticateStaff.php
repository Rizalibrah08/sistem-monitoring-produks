<?php

namespace App\Actions;

use App\Models\User;
use App\Services\AuditLogger;
use App\StaffRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

class AuthenticateStaff
{
    public function __construct(private AuditLogger $audit, private Timebox $timebox) {}

    public function handle(string $email, #[\SensitiveParameter] string $password): User
    {
        $result = $this->timebox->call(function (Timebox $timebox) use ($email, $password): array {
            return DB::transaction(function () use ($email, $password, $timebox): array {
                $user = User::query()->with('roles')->where('email', $email)->lockForUpdate()->first();
                $unknownKey = 'login:unknown:'.hash('sha256', $email);

                if ($user?->locked_until?->isFuture() || ($user === null && RateLimiter::tooManyAttempts($unknownKey, 5))) {
                    $this->audit->record('auth.login_locked', $user, after: ['email' => $email]);

                    return ['user' => null, 'locked' => true];
                }

                if ($user?->locked_until !== null) {
                    $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null]);
                }

                $provider = Auth::guard('web')->getProvider();
                $validPassword = $user !== null && $provider->validateCredentials($user, ['password' => $password]);

                if ($validPassword && $user->is_active && $user->hasAnyRole(StaffRole::cases())) {
                    $provider->rehashPasswordIfRequired($user, ['password' => $password]);
                    $before = ['last_login_at' => $user->last_login_at?->format('Y-m-d H:i:s')];
                    $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null, 'last_login_at' => now()])->save();
                    $this->audit->record('auth.login', $user, before: $before, after: ['last_login_at' => now()->format('Y-m-d H:i:s')], actor: $user);
                    $timebox->returnEarly();

                    return ['user' => $user, 'locked' => false];
                }

                $locked = false;

                if ($user !== null) {
                    $attempts = $user->failed_login_attempts + 1;
                    $locked = $attempts >= 5;
                    $user->forceFill(['failed_login_attempts' => $attempts, 'locked_until' => $locked ? now()->addMinutes(15) : null])->save();
                } else {
                    RateLimiter::hit($unknownKey, 900);
                    $locked = RateLimiter::tooManyAttempts($unknownKey, 5);
                }

                $this->audit->record('auth.login_failed', $user, after: ['email' => $email]);

                return ['user' => null, 'locked' => $locked];
            }, attempts: 5);
        }, 200000);

        if ($result['user'] === null) {
            throw ValidationException::withMessages([
                'email' => $result['locked']
                    ? 'Terlalu banyak percobaan masuk. Coba lagi setelah 15 menit.'
                    : 'Email atau kata sandi salah, atau akun tidak aktif.',
            ]);
        }

        return $result['user'];
    }
}
