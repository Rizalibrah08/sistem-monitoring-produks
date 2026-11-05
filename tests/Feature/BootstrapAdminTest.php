<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_initial_admin_is_created_with_an_explicit_password_and_a_system_audit_entry(): void
    {
        $this->artisan('app:create-admin', ['email' => ' ADMIN@AISHA.TEST ', '--name' => 'Admin Aisha'])
            ->expectsQuestion('Kata sandi', 'AishaStrong123!')
            ->assertSuccessful();

        $user = User::query()->sole();
        $this->assertSame('admin@aisha.test', $user->email);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue(Hash::check('AishaStrong123!', $user->password));
        $this->assertDatabaseHas('activity_log', ['event' => 'user.bootstrap_admin', 'subject_id' => $user->id, 'source' => 'system', 'causer_id' => null]);
    }

    public function test_bootstrapping_never_overwrites_an_existing_account(): void
    {
        $existing = User::factory()->create(['email' => 'admin@aisha.test']);

        $this->artisan('app:create-admin', ['email' => 'admin@aisha.test', '--name' => 'Admin Baru'])
            ->expectsQuestion('Kata sandi', 'AishaStrong123!')
            ->assertFailed();

        $this->assertSame($existing->name, $existing->fresh()->name);
        $this->assertTrue(Hash::check('password', $existing->fresh()->password));
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_additional_admins_must_be_created_through_the_authorized_application(): void
    {
        $this->seed();
        $existing = User::factory()->create();
        $existing->assignRole('admin');

        $this->artisan('app:create-admin', ['email' => 'second@aisha.test', '--name' => 'Admin Kedua'])
            ->expectsQuestion('Kata sandi', 'AishaStrong123!')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_weak_bootstrap_passwords_are_rejected_without_creating_accounts(): void
    {
        $this->artisan('app:create-admin', ['email' => 'admin@aisha.test', '--name' => 'Admin Aisha'])
            ->expectsQuestion('Kata sandi', 'password')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('activity_log', 0);
    }
}
