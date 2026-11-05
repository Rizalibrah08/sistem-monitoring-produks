<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_outside_marketing_and_manager_cannot_view_or_change_clients(): void
    {
        $regulatory = $this->createStaff('ra');

        $this->actingAs($regulatory)->get('/clients')->assertForbidden();
        $this->post('/clients', $this->validClient())->assertForbidden();

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_marketing_adds_a_client_whose_whatsapp_number_is_normalized_and_audited(): void
    {
        $marketing = $this->createStaff('marketing');

        $this->actingAs($marketing)->from('/clients')->post('/clients', $this->validClient([
            'whatsapp' => '0812-3456-7890',
            'marketing_user_id' => null,
        ]))->assertRedirect('/clients')->assertSessionHasNoErrors();

        $client = Client::query()->sole();
        $this->assertSame('6281234567890', $client->whatsapp);
        $this->assertSame($marketing->id, $client->marketing_user_id);
        $this->assertDatabaseHas('activity_log', ['event' => 'client.created', 'subject_id' => $client->id, 'causer_id' => $marketing->id]);
    }

    public function test_each_whatsapp_number_belongs_to_one_client_in_any_written_form(): void
    {
        $marketing = $this->createStaff('marketing');
        Client::factory()->create(['whatsapp' => '6281234567890']);

        $this->actingAs($marketing)->from('/clients')->post('/clients', $this->validClient(['whatsapp' => '+62 812 3456 7890']))
            ->assertSessionHasErrors(['whatsapp' => 'Nomor WhatsApp ini sudah dipakai klien lain.']);
        $this->post('/clients', $this->validClient(['whatsapp' => '12345']))
            ->assertSessionHasErrors(['whatsapp' => 'Nomor WhatsApp harus nomor Indonesia, misalnya 0812xxxxxxxx.']);
        $this->post('/clients', $this->validClient(['name' => '', 'pic_name' => '', 'email' => 'bukan-email']))
            ->assertSessionHasErrors(['name', 'pic_name', 'email']);

        $this->assertDatabaseCount('clients', 1);
    }

    public function test_the_person_in_charge_must_be_an_active_marketing_staff_member(): void
    {
        $manager = $this->createStaff('manager');
        $finance = $this->createStaff('finance', ['email' => 'finance@aisha.test']);

        $this->actingAs($manager)->from('/clients')->post('/clients', $this->validClient(['marketing_user_id' => $finance->id]))
            ->assertSessionHasErrors(['marketing_user_id' => 'PIC Marketing harus staf Marketing yang aktif.']);
        $this->post('/clients', $this->validClient(['marketing_user_id' => null]))
            ->assertSessionHasErrors(['marketing_user_id' => 'Pilih PIC Marketing untuk klien ini.']);

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_changing_the_whatsapp_number_is_audited_with_the_old_and_new_number(): void
    {
        $marketing = $this->createStaff('marketing');
        $client = Client::factory()->create(['whatsapp' => '6281111111111', 'marketing_user_id' => $marketing->id]);

        $this->actingAs($marketing)->from('/clients')->put("/clients/{$client->id}", $this->validClient([
            'name' => $client->name,
            'whatsapp' => '081222222222',
            'marketing_user_id' => $marketing->id,
        ]))->assertRedirect('/clients')->assertSessionHasNoErrors();

        $this->assertSame('6281222222222', $client->fresh()->whatsapp);
        $entry = Activity::query()->where('event', 'client.updated')->sole();
        $this->assertSame('6281111111111', $entry->attribute_changes['old']['whatsapp']);
        $this->assertSame('6281222222222', $entry->attribute_changes['attributes']['whatsapp']);
    }

    public function test_marketing_sees_clients_with_their_person_in_charge_and_can_search_them(): void
    {
        $marketing = $this->createStaff('marketing', ['name' => 'Rizal Marketing']);
        Client::factory()->create(['name' => 'Nusantara Herbal', 'marketing_user_id' => $marketing->id]);
        Client::factory()->create(['name' => 'Bumi Sehat', 'marketing_user_id' => $marketing->id]);

        $this->withoutVite()->actingAs($marketing)->get('/clients?search=nusantara')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Clients', false)
            ->has('clients.data', 1)
            ->where('clients.data.0.name', 'Nusantara Herbal')
            ->where('clients.data.0.marketing_user', 'Rizal Marketing')
            ->where('filters.search', 'nusantara')
            ->where('marketingStaff', fn ($staff): bool => collect($staff)->pluck('label')->contains('Rizal Marketing'))
            ->where('navigation', fn ($items): bool => collect($items)->contains(fn (array $item): bool => $item['id'] === 'clients' && $item['available'] === true)));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validClient(array $overrides = []): array
    {
        return [
            'name' => 'Nusantara Herbal',
            'company' => 'CV Nusantara Herbal',
            'pic_name' => 'Nadia Putri',
            'whatsapp' => '081234567890',
            'email' => 'nadia@nusantara.test',
            'marketing_user_id' => null,
            ...$overrides,
        ];
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
