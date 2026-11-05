<?php

namespace App\Http\Resources;

use App\StaffRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->getRoleNames()->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role,
            'role_label' => StaffRole::tryFrom($role ?? '')?->label(),
            'is_active' => $this->is_active,
            'unavailable_from' => $this->unavailable_from?->format('Y-m-d'),
            'unavailable_until' => $this->unavailable_until?->format('Y-m-d'),
            'unavailable' => $this->isUnavailable(),
            'last_login_at' => $this->last_login_at?->format('d/m/Y H:i'),
            'update_url' => route('users.update', $this->resource, absolute: false),
        ];
    }
}
