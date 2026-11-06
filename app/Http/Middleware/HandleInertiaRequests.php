<?php

namespace App\Http\Middleware;

use App\Services\WorkspaceNavigation;
use App\StaffRole;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(private WorkspaceNavigation $navigation) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => function () use ($request): array {
                $user = $request->user();

                if ($user === null) {
                    return ['user' => null, 'permissions' => []];
                }

                $role = $user->getRoleNames()->first();

                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $role,
                        'role_label' => StaffRole::tryFrom($role ?? '')?->label(),
                        'is_active' => $user->is_active,
                        'unavailable' => $user->isUnavailable(),
                    ],
                    'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
                ];
            },
            'navigation' => fn (): array => $request->user() !== null ? $this->navigation->forUser($request->user()) : [],
            'routes' => [
                'login' => route('login', absolute: false),
                'loginStore' => route('login.store', absolute: false),
                'logout' => route('logout', absolute: false),
                'dashboard' => route('dashboard', absolute: false),
                'users' => route('users.index', absolute: false),
                'usersStore' => route('users.store', absolute: false),
                'audit' => route('audit.index', absolute: false),
                'master' => route('master.index', absolute: false),
                'documentRules' => route('document-rules.index', absolute: false),
                'clients' => route('clients.index', absolute: false),
                'tickets' => route('tickets.index', absolute: false),
                'notifications' => route('notifications.index', absolute: false),
            ],
            'workspace' => [
                'search_available' => false,
                'whatsapp_connected' => false,
                'notifications_available' => true,
                'notifications_unread' => fn (): int => $request->user()?->unreadNotifications()->count() ?? 0,
            ],
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
                'upload_link' => fn (): ?string => $request->session()->get('upload_link'),
            ],
        ];
    }
}
