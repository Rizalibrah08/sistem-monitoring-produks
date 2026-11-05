<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_cannot_read_audit_entries(): void
    {
        $this->get('/audit')->assertRedirectToRoute('login');
    }

    public function test_staff_without_audit_permission_cannot_read_the_log(): void
    {
        $user = $this->createStaff('ra');

        $this->actingAs($user)->get('/audit')->assertForbidden();
    }

    public function test_manager_can_filter_audit_entries_by_actor_event_ticket_and_dates(): void
    {
        $this->travelTo('2026-11-05 08:00:00');
        $manager = $this->createStaff('manager');
        $actor = $this->createStaff('ra', ['name' => 'Petugas RA']);
        $entry = activity('tickets')->causedBy($actor)->event('ticket.created')
            ->withChanges(['old' => [], 'attributes' => ['product' => 'Produk Uji']])
            ->tap(function (Activity $activity): void {
                $activity->ticket_code = 'PRD-2026-0012';
                $activity->source = 'web';
            })->log('Tiket dibuat');
        activity('access')->causedBy($manager)->event('auth.login')->log('Masuk');

        $query = http_build_query(['actor_id' => $actor->id, 'event' => 'ticket.created', 'ticket_code' => 'PRD-2026-0012', 'date_from' => '2026-11-05', 'date_to' => '2026-11-05']);
        $this->withoutVite()->actingAs($manager)->get('/audit?'.$query)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backend/Audit', false)
                ->has('entries.data', 1)
                ->where('entries.data.0.id', $entry->id)
                ->where('entries.data.0.actor_name', 'Petugas RA')
                ->where('entries.data.0.actor_role', 'ra')
                ->where('entries.data.0.ticket_code', 'PRD-2026-0012')
                ->where('entries.data.0.hash', $entry->hash)
                ->where('entries.data.0.after.product', 'Produk Uji')
                ->has('navigation', 9));
    }

    public function test_admin_can_view_audit_entries_without_mutation_endpoints(): void
    {
        $admin = $this->createStaff('admin');
        $entry = activity()->log('Aksi asli');

        $this->withoutVite()->actingAs($admin)->get('/audit')->assertOk();
        $this->post('/audit', ['description' => 'Palsu'])->assertMethodNotAllowed();
        $this->delete('/audit/'.$entry->id)->assertNotFound();

        $this->assertDatabaseHas('activity_log', ['id' => $entry->id, 'description' => 'Aksi asli']);
    }

    public function test_audit_date_filters_reject_inverted_date_ranges(): void
    {
        $manager = $this->createStaff('manager');

        $this->actingAs($manager)->from('/audit')->get('/audit?date_from=2026-11-07&date_to=2026-11-05')
            ->assertRedirect('/audit')->assertSessionHasErrors('date_to');
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
