<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_cannot_access_user_management(): void
    {
        $this->get('/users')->assertRedirectToRoute('login');
    }

    public function test_non_admin_staff_cannot_list_or_create_users_even_when_spoofing_a_role(): void
    {
        $user = $this->createStaff('marketing');

        $this->actingAs($user)->get('/users?role=admin')->assertForbidden();
        $this->post('/users', $this->validUser())->assertForbidden();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_admin_can_list_filtered_users_without_exposing_credentials(): void
    {
        $admin = $this->createStaff('admin');
        $target = $this->createStaff('qa', ['email' => 'quality@aisha.test']);

        $this->withoutVite()->actingAs($admin)->get('/users?search=quality&role=qa')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Users', false)
            ->has('users.data', 1)
            ->where('users.data.0.id', $target->id)
            ->where('users.data.0.role', 'qa')
            ->missing('users.data.0.password')
            ->missing('users.data.0.remember_token')
            ->has('roles', 7)
            ->has('navigation', 5));
    }

    public function test_admin_can_create_an_active_staff_account_with_one_role_and_an_audit_entry(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->post('/users', $this->validUser(['email' => ' QA@AISHA.TEST ', 'permissions' => ['users.manage']]))
            ->assertRedirectToRoute('users.index')->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'qa@aisha.test')->firstOrFail();
        $this->assertSame(['qa'], $user->getRoleNames()->all());
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('AishaStrong123!', $user->password));
        $this->assertStringStartsWith('$2y$', $user->password);
        $this->assertFalse($user->can('users.manage'));
        $this->assertDatabaseHas('activity_log', ['event' => 'user.created', 'subject_id' => $user->id, 'causer_id' => $admin->id]);
        $this->assertStringNotContainsString('AishaStrong123!', Activity::query()->firstOrFail()->toJson());
    }

    public function test_creation_requires_valid_fields_and_does_not_create_partial_records(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/users')->post('/users', [])->assertSessionHasErrors(['name', 'email', 'password', 'role', 'is_active']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_client_is_not_a_valid_internal_account_role(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->post('/users', $this->validUser(['role' => 'client']))->assertSessionHasErrors('role');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_duplicate_normalized_emails_are_rejected(): void
    {
        $admin = $this->createStaff('admin', ['email' => 'admin@aisha.test']);

        $this->actingAs($admin)->post('/users', $this->validUser(['email' => ' ADMIN@AISHA.TEST ']))
            ->assertSessionHasErrors(['email' => 'Email sudah digunakan.']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_short_passwords_are_rejected_before_hashing(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->post('/users', $this->validUser(['password' => 'Short123', 'password_confirmation' => 'Short123']))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_multibyte_passwords_over_seventy_two_bytes_are_rejected_before_hashing(): void
    {
        $admin = $this->createStaff('admin');
        $password = str_repeat('é', 36).'A1';

        $this->actingAs($admin)->post('/users', $this->validUser(['password' => $password, 'password_confirmation' => $password]))
            ->assertSessionHasErrors(['password' => 'Kata sandi maksimal 72 byte.']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_passwords_containing_null_bytes_are_rejected_before_hashing(): void
    {
        $admin = $this->createStaff('admin');
        $password = "StrongPassword123\0";

        $this->actingAs($admin)->post('/users', $this->validUser(['password' => $password, 'password_confirmation' => $password]))
            ->assertSessionHasErrors(['password' => 'Kata sandi tidak boleh mengandung karakter nol.']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_change_a_role_and_schedule_unavailability_with_before_and_after_audit_values(): void
    {
        $this->travelTo('2026-11-05 10:00:00');
        $admin = $this->createStaff('admin');
        $target = $this->createStaff('marketing');

        $this->actingAs($admin)->put('/users/'.$target->id, $this->validUser([
            'name' => 'Petugas Penelitian', 'email' => $target->email, 'role' => 'rnd',
            'password' => null, 'password_confirmation' => null,
            'unavailable_from' => '2026-11-05', 'unavailable_until' => '2026-11-07',
        ]))->assertRedirectToRoute('users.index')->assertSessionHasNoErrors();

        $this->assertSame(['rnd'], $target->fresh()->getRoleNames()->all());
        $this->assertTrue($target->fresh()->isUnavailable());
        $this->assertTrue(Hash::check('password', $target->fresh()->password));
        $entry = Activity::query()->where('event', 'user.updated')->firstOrFail();
        $this->assertSame('marketing', $entry->attribute_changes['old']['role']);
        $this->assertSame('rnd', $entry->attribute_changes['attributes']['role']);
        $this->assertSame('admin', $entry->actor_role);
    }

    public function test_an_unavailability_range_requires_both_dates_in_chronological_order(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->post('/users', $this->validUser(['unavailable_from' => '2026-11-08', 'unavailable_until' => '2026-11-05']))
            ->assertSessionHasErrors('unavailable_until');
        $this->post('/users', $this->validUser(['unavailable_from' => '2026-11-08', 'unavailable_until' => null]))
            ->assertSessionHasErrors('unavailable_until');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_can_deactivate_another_account_without_deleting_its_history(): void
    {
        $admin = $this->createStaff('admin');
        $target = $this->createStaff('qa');

        $this->actingAs($admin)->put('/users/'.$target->id, $this->validUser([
            'email' => $target->email, 'is_active' => false, 'password' => null, 'password_confirmation' => null,
        ]))->assertRedirectToRoute('users.index');

        $this->assertFalse($target->fresh()->is_active);
        $this->assertModelExists($target);
        $this->assertDatabaseHas('activity_log', ['event' => 'user.updated', 'subject_id' => $target->id]);
    }

    public function test_admin_cannot_deactivate_or_demote_their_own_account(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->put('/users/'.$admin->id, $this->validUser([
            'email' => $admin->email, 'role' => 'admin', 'is_active' => false,
        ]))->assertSessionHasErrors('is_active');
        $this->put('/users/'.$admin->id, $this->validUser(['email' => $admin->email, 'role' => 'ra']))
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_a_failed_audit_write_rolls_back_user_creation_and_role_assignment(): void
    {
        $admin = $this->createStaff('admin');
        DB::table('audit_log_locks')->delete();

        $this->actingAs($admin)->post('/users', $this->validUser())->assertInternalServerError();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('model_has_roles', 1);
    }

    public function test_unknown_user_updates_are_not_found_and_public_registration_is_not_available(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->put('/users/99999', $this->validUser())->assertNotFound();
        $this->get('/register')->assertNotFound();

        $this->assertDatabaseCount('users', 1);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validUser(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Petugas Mutu', 'email' => 'qa@aisha.test', 'role' => 'qa', 'is_active' => true,
            'password' => 'AishaStrong123!', 'password_confirmation' => 'AishaStrong123!',
            'unavailable_from' => null, 'unavailable_until' => null,
        ], $overrides);
    }

    /** @param array<string, mixed> $attributes */
    private function createStaff(string $role, array $attributes = []): User
    {
        $this->seed();
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
