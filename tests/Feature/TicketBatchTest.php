<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Client;
use App\Models\DocumentType;
use App\Models\DosageForm;
use App\Models\Ticket;
use App\Models\User;
use App\StagePhase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TicketBatchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $marketing;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 10:00:00');
        $this->seed();
        $this->marketing = User::factory()->create(['email' => 'marketing@aisha.test']);
        $this->marketing->assignRole('marketing');
        $this->client = Client::factory()->create(['marketing_user_id' => $this->marketing->id]);
        DocumentType::factory()->create(['name' => 'Master formula', 'requirement' => DocumentRequirement::Required, 'phase' => StagePhase::PreRegistration]);
    }

    public function test_marketing_creates_one_ticket_per_product_for_a_client_at_once(): void
    {
        $this->actingAs($this->marketing)->post('/tickets/batch', [
            'client_id' => $this->client->id,
            'products' => [$this->product('Kapsul Jahe Merah'), $this->product('Sirup Temulawak'), $this->product('Serbuk Kunyit')],
        ])->assertRedirect('/tickets')->assertSessionHas('status', '3 tiket dibuat: PRD-2026-0001, PRD-2026-0002, PRD-2026-0003.');

        $tickets = Ticket::query()->orderBy('code')->get();
        $this->assertSame(['Kapsul Jahe Merah', 'Sirup Temulawak', 'Serbuk Kunyit'], $tickets->pluck('product_name')->all());
        $this->assertSame([$this->client->id], $tickets->pluck('client_id')->unique()->values()->all());
        $tickets->each(fn (Ticket $ticket) => $this->assertSame(1, $ticket->documents()->count()));
        $this->assertSame(3, Activity::query()->where('event', 'ticket.created')->count());
    }

    public function test_every_product_is_validated_and_nothing_is_created_when_one_is_invalid(): void
    {
        $this->actingAs($this->marketing)->from('/tickets')->post('/tickets/batch', [
            'client_id' => $this->client->id,
            'products' => [$this->product('Kapsul Jahe Merah'), $this->product('', ['dosage_form_id' => 999999])],
        ])->assertSessionHasErrors(['products.1.product_name' => 'Nama produk wajib diisi.', 'products.1.dosage_form_id' => 'Sediaan harus dipilih dari daftar sediaan aktif.']);

        $this->post('/tickets/batch', ['client_id' => $this->client->id, 'products' => [$this->product('Satu saja')]])
            ->assertSessionHasErrors(['products' => 'Pembuatan sekaligus membutuhkan paling sedikit dua produk.']);

        $this->post('/tickets/batch', ['client_id' => Client::factory()->create()->id, 'products' => [$this->product('A'), $this->product('B')]])
            ->assertSessionHasErrors(['client_id' => 'Pilih klien yang Anda tangani sebagai PIC Marketing.']);

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_only_staff_who_create_tickets_may_create_them_in_batches(): void
    {
        $regulatory = User::factory()->create(['email' => 'ra@aisha.test']);
        $regulatory->assignRole('ra');

        $this->actingAs($regulatory)->post('/tickets/batch', ['client_id' => $this->client->id, 'products' => [$this->product('A'), $this->product('B')]])->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function product(string $name, array $overrides = []): array
    {
        return ['product_name' => $name, 'dosage_form_id' => DosageForm::factory()->create()->id, 'category_id' => Category::factory()->create()->id, ...$overrides];
    }
}
