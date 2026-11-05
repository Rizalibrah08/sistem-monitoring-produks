<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
        'failed_login_attempts' => 0,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'unavailable_from' => 'date',
            'unavailable_until' => 'date',
            'last_login_at' => 'datetime',
            'failed_login_attempts' => 'integer',
            'locked_until' => 'datetime',
        ];
    }

    public function isUnavailable(): bool
    {
        return $this->unavailable_from !== null
            && $this->unavailable_until !== null
            && today()->betweenIncluded($this->unavailable_from, $this->unavailable_until);
    }
}
