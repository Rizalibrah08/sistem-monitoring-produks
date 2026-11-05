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
        $this->assertDatabaseCount('permissions', 5);
        $this->assertDatabaseCount('users', 0);
    }

    #[DataProvider('rolePermissions')]
    public function test_role_permissions_match_the_foundation_access_matrix(string $role, bool $manageUsers, bool $viewAudit, bool $manageMasterData, bool $createMaterials): void
    {
        $this->seed();
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->assertTrue(Gate::forUser($user)->allows('dashboard.view'));
        $this->assertSame($manageUsers, Gate::forUser($user)->allows('users.manage'));
        $this->assertSame($viewAudit, Gate::forUser($user)->allows('audit.view'));
        $this->assertSame($manageMasterData, Gate::forUser($user)->allows('master.manage'));
        $this->assertSame($createMaterials, Gate::forUser($user)->allows('materials.create'));
    }

    /** @return array<string, array{string, bool, bool, bool, bool}> */
    public static function rolePermissions(): array
    {
        return [
            'admin' => ['admin', true, true, true, true],
            'manager' => ['manager', false, true, false, false],
            'marketing' => ['marketing', false, false, false, false],
            'research' => ['rnd', false, false, false, true],
            'quality' => ['qa', false, false, false, false],
            'regulatory' => ['ra', false, false, false, false],
            'finance' => ['finance', false, false, false, false],
        ];
    }
}
