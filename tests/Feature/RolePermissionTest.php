<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeding_creates_seven_internal_roles_without_a_default_password_account(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
        $this->assertSame(['admin', 'finance', 'manager', 'marketing', 'qa', 'ra', 'rnd'], Role::query()->orderBy('name')->pluck('name')->all());
    }

    public function test_role_seeding_can_be_repeated_without_duplicates(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('roles', 7);
        $this->assertDatabaseCount('permissions', count(self::ALL_PERMISSIONS));
        $this->assertDatabaseCount('users', 0);
    }

    /** @var list<string> */
    private const ALL_PERMISSIONS = [
        'audit.view', 'clients.manage', 'dashboard.view', 'document_rules.manage', 'master.manage',
        'materials.create', 'ticket_materials.manage', 'tickets.create', 'tickets.view', 'upload_links.manage', 'users.manage',
    ];

    /** @param list<string> $granted */
    #[DataProvider('rolePermissions')]
    public function test_role_permissions_match_the_access_matrix(string $role, array $granted): void
    {
        $this->seed();
        $user = User::factory()->create();
        $user->assignRole($role);

        foreach (self::ALL_PERMISSIONS as $permission) {
            $this->assertSame(in_array($permission, $granted, true), Gate::forUser($user)->allows($permission), "{$role} · {$permission}");
        }
    }

    /** @return array<string, array{string, list<string>}> */
    public static function rolePermissions(): array
    {
        return [
            'admin' => ['admin', ['dashboard.view', 'users.manage', 'audit.view', 'master.manage', 'materials.create', 'document_rules.manage']],
            'manager' => ['manager', ['dashboard.view', 'audit.view', 'clients.manage', 'tickets.view']],
            'marketing' => ['marketing', ['dashboard.view', 'clients.manage', 'tickets.view', 'tickets.create', 'upload_links.manage']],
            'research' => ['rnd', ['dashboard.view', 'materials.create', 'tickets.view', 'ticket_materials.manage']],
            'quality' => ['qa', ['dashboard.view', 'tickets.view']],
            'regulatory' => ['ra', ['dashboard.view', 'document_rules.manage', 'tickets.view', 'upload_links.manage']],
            'finance' => ['finance', ['dashboard.view']],
        ];
    }
}
