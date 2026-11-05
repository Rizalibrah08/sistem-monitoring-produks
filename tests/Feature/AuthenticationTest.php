<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_is_a_real_login_form_without_a_role_picker(): void
    {
        $this->withoutVite()->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login', false)
            ->missing('initialRole')
            ->where('auth.user', null));
    }

    public function test_guests_are_redirected_to_login_before_seeing_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirectToRoute('login');
    }

    public function test_active_staff_can_login_with_normalized_email_and_the_attempt_is_audited(): void
    {
        $this->travelTo('2026-11-05 08:00:00');
        $user = $this->createStaff('ra', ['email' => 'petugas@aisha.test']);

        $this->from('/login')->post('/login', ['email' => ' PETUGAS@AISHA.TEST ', 'password' => 'password', 'role' => 'admin'])
            ->assertRedirectToRoute('dashboard')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('last_activity_at', now()->timestamp);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_log', ['event' => 'auth.login', 'causer_id' => $user->id, 'actor_role' => 'ra', 'source' => 'web']);
        $this->assertSame('2026-11-05 08:00:00', $user->fresh()->last_login_at->format('Y-m-d H:i:s'));
    }

    public function test_invalid_credentials_do_not_authenticate_and_are_audited_without_the_password(): void
    {
        $user = $this->createStaff();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-secret'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah, atau akun tidak aktif.']);

        $this->assertGuest();
        $this->assertSame(1, $user->fresh()->failed_login_attempts);
        $this->assertDatabaseHas('activity_log', ['event' => 'auth.login_failed', 'subject_id' => $user->id]);
        $this->assertStringNotContainsString('wrong-secret', Activity::query()->firstOrFail()->toJson());
    }

    public function test_inactive_accounts_cannot_login_even_with_the_correct_password(): void
    {
        $user = $this->createStaff('ra', ['is_active' => false]);

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah, atau akun tidak aktif.']);

        $this->assertGuest();
        $this->assertDatabaseMissing('activity_log', ['event' => 'auth.login']);
    }

    public function test_accounts_without_an_internal_role_cannot_login(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_five_consecutive_failures_lock_the_account_for_fifteen_minutes_even_from_another_ip(): void
    {
        $this->travelTo('2026-11-05 08:00:00');
        $user = $this->createStaff();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->travel(10)->minutes();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertSame('2026-11-05 08:25:00', $user->fresh()->locked_until->format('Y-m-d H:i:s'));

        $this->travel(14)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.12'])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(1)->minutes();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirectToRoute('dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $user->fresh()->failed_login_attempts);
        $this->assertNull($user->fresh()->locked_until);
    }

    public function test_a_successful_login_resets_the_consecutive_failure_counter(): void
    {
        $user = $this->createStaff();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirectToRoute('dashboard');

        $this->assertSame(0, $user->fresh()->failed_login_attempts);
    }

    public function test_login_requires_email_and_password_with_indonesian_validation_messages(): void
    {
        $this->from('/login')->post('/login', [])->assertSessionHasErrors([
            'email' => 'Email wajib diisi.',
            'password' => 'Kata sandi wajib diisi.',
        ]);

        $this->assertGuest();
    }

    public function test_login_rejects_passwords_over_bcrypt_byte_limit_instead_of_crashing(): void
    {
        $this->from('/login')->post('/login', ['email' => 'petugas@aisha.test', 'password' => str_repeat('é', 37)])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['password' => 'Kata sandi maksimal 72 byte.']);

        $this->assertGuest();
    }

    public function test_deactivated_accounts_lose_access_on_their_next_authenticated_request(): void
    {
        $user = $this->createStaff('ra', ['is_active' => false]);

        $this->actingAs($user)->get('/dashboard')->assertRedirectToRoute('login');

        $this->assertGuest();
        $this->assertDatabaseHas('activity_log', ['event' => 'auth.access_revoked', 'causer_id' => $user->id]);
    }

    public function test_sessions_expire_after_sixty_minutes_of_inactivity(): void
    {
        $this->travelTo('2026-11-05 09:00:00');
        $user = $this->createStaff();

        $this->actingAs($user)->withSession(['last_activity_at' => now()->subHour()->timestamp])
            ->get('/dashboard')->assertRedirectToRoute('login');

        $this->assertGuest();
        $this->assertDatabaseHas('activity_log', ['event' => 'auth.session_expired', 'causer_id' => $user->id]);
    }

    public function test_active_sessions_share_only_the_authenticated_role_and_safe_user_fields(): void
    {
        $this->travelTo('2026-11-05 09:00:00');
        $user = $this->createStaff();

        $this->assertTrue($user->fresh()->is_active);
        $this->assertTrue($user->is_active);

        $this->withoutVite()->actingAs($user)->withSession(['last_activity_at' => now()->subMinutes(59)->timestamp])
            ->get('/dashboard?role=admin')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Dashboard', false)
            ->where('auth.user.id', $user->id)
            ->where('auth.user.role', 'ra')
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
            ->where('auth.permissions', ['dashboard.view'])
            ->where('navigation.0.label', 'Dasbor')
            ->has('navigation', 8));
    }

    public function test_logout_invalidates_the_session_and_records_the_actor(): void
    {
        $user = $this->createStaff();

        $this->actingAs($user)->withSession(['private_state' => 'private'])->post('/logout')
            ->assertRedirectToRoute('login')->assertSessionMissing('private_state');

        $this->assertGuest();
        $this->assertDatabaseHas('activity_log', ['event' => 'auth.logout', 'causer_id' => $user->id]);
    }

    public function test_failed_audit_writes_do_not_leave_an_authenticated_session(): void
    {
        $user = $this->createStaff();
        DB::table('audit_log_locks')->delete();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertInternalServerError();

        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    /** @param array<string, mixed> $attributes */
    private function createStaff(string $role = 'ra', array $attributes = []): User
    {
        $this->seed();
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
