<?php

namespace App\Http\Controllers;

use App\Actions\SaveStaffUser;
use App\Http\Requests\SaveUserRequest;
use App\Http\Resources\StaffUserResource;
use App\Models\User;
use App\StaffRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(StaffRole::class)],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'unavailable'])],
        ]);

        $users = User::query()->with('roles')
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('name', 'like', '%'.$filters['search'].'%')->orWhere('email', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when(filled($filters['role'] ?? null), fn (Builder $query) => $query->role($filters['role']))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'unavailable', fn (Builder $query) => $query->where('is_active', true)->whereDate('unavailable_from', '<=', today())->whereDate('unavailable_until', '>=', today()))
            ->orderBy('name')->orderBy('id')->paginate(25)->withQueryString()
            ->through(fn (User $user): array => (new StaffUserResource($user))->resolve($request));

        return Inertia::render('Backend/Users', [
            'users' => $users,
            'roles' => array_map(fn (StaffRole $role): array => ['value' => $role->value, 'label' => $role->label()], StaffRole::cases()),
            'filters' => ['search' => $filters['search'] ?? '', 'role' => $filters['role'] ?? '', 'status' => $filters['status'] ?? ''],
        ]);
    }

    public function store(SaveUserRequest $request, SaveStaffUser $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user());

        return redirect()->route('users.index')->with('status', 'Pengguna berhasil dibuat.');
    }

    public function update(SaveUserRequest $request, User $user, SaveStaffUser $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user(), $user);

        return redirect()->route('users.index')->with('status', 'Pengguna berhasil diperbarui.');
    }
}
