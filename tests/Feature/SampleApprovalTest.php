<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Client;
use App\Models\SampleApproval;
use App\Models\Stage;
use App\Models\Ticket;
use App\Models\User;
use App\SampleDecision;
use App\StagePhase;
use App\TicketStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SampleApprovalTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $marketing;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 10:00:00');
        $this->marketing = $this->createStaff('marketing');
        $sampleStage = Stage::query()->where('code', 'pengembangan_sampel')->sole();
        $this->ticket = Ticket::factory()->create([
            'client_id' => Client::factory()->create(['marketing_user_id' => $this->marketing->id])->id,
            'current_stage_id' => $sampleStage->id,
        ]);
        $this->ticket->stageLogs()->create(['stage_id' => $sampleStage->id, 'started_at' => now()->subDays(3)]);
    }

    public function test_marketing_records_revisions_with_increasing_numbers_and_notes(): void
    {
        $this->actingAs($this->marketing)->from("/tickets/{$this->ticket->id}")
            ->post("/tickets/{$this->ticket->id}/samples", ['decision' => 'perlu_revisi', 'note' => 'Rasa terlalu pahit.'])
            ->assertRedirect("/tickets/{$this->ticket->id}")->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Revisi sampel ke-1 dicatat.');
        $this->travel(10)->minutes();
        $this->post("/tickets/{$this->ticket->id}/samples", ['decision' => 'perlu_revisi', 'note' => 'Warna perlu lebih terang.'])->assertSessionHasNoErrors();

        $approvals = $this->ticket->sampleApprovals()->orderBy('revision')->get();
        $this->assertSame([1, 2], $approvals->pluck('revision')->all());
        $this->assertSame(SampleDecision::NeedsRevision, $approvals[1]->decision);
        $this->assertSame('Warna perlu lebih terang.', $approvals[1]->note);
        $this->assertSame($this->marketing->id, $approvals[1]->recorded_by);
        $this->assertSame('pengembangan_sampel', $this->ticket->refresh()->currentStage->code);

        $entry = Activity::query()->where('event', 'sample.recorded')->latest('id')->first();
        $this->assertSame($this->ticket->code, $entry->ticket_code);
        $this->assertSame(2, $entry->attribute_changes['attributes']['revision']);
    }

    public function test_approval_moves_the_ticket_to_the_contract_stage_and_closes_further_decisions(): void
    {
        SampleApproval::factory()->create(['ticket_id' => $this->ticket->id, 'recorded_by' => $this->marketing->id]);

        $this->actingAs($this->marketing)->post("/tickets/{$this->ticket->id}/samples", ['decision' => 'disetujui', 'note' => ''])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Sampel revisi 2 disetujui klien.');

        $approval = $this->ticket->sampleApprovals()->latest('revision')->first();
        $this->assertSame(SampleDecision::Approved, $approval->decision);
        $this->assertNull($approval->note);
        $this->ticket->refresh();
        $this->assertSame('kontrak', $this->ticket->currentStage->code);
        $logs = $this->ticket->stageLogs()->with('stage')->orderBy('id')->get();
        $this->assertSame(['pengembangan_sampel', 'kontrak'], $logs->pluck('stage.code')->all());
        $this->assertNotNull($logs[0]->finished_at);
        $this->assertNull($logs[1]->finished_at);
        $this->assertSame('Kontrak (MoU)', Activity::query()->where('event', 'sample.recorded')->sole()->attribute_changes['attributes']['next_stage']);

        $this->from("/tickets/{$this->ticket->id}")->post("/tickets/{$this->ticket->id}/samples", ['decision' => 'perlu_revisi', 'note' => 'Berubah pikiran.'])
            ->assertSessionHasErrors(['decision' => 'Sampel tiket ini sudah disetujui.']);
        $this->assertSame(2, $this->ticket->sampleApprovals()->count());
    }

    public function test_a_revision_needs_a_note_and_the_decision_must_be_known(): void
    {
        $this->actingAs($this->marketing)->from("/tickets/{$this->ticket->id}")
            ->post("/tickets/{$this->ticket->id}/samples", ['decision' => 'perlu_revisi', 'note' => ''])
            ->assertSessionHasErrors(['note' => 'Catatan revisi wajib diisi.']);
        $this->post("/tickets/{$this->ticket->id}/samples", ['decision' => 'ditunda'])
            ->assertSessionHasErrors(['decision' => 'Keputusan harus disetujui atau perlu revisi.']);

        $this->assertSame(0, $this->ticket->sampleApprovals()->count());
    }

    public function test_only_the_marketing_staff_of_the_client_records_decisions_on_active_tickets(): void
    {
        foreach (['ra', 'rnd', 'manager'] as $role) {
            $this->actingAs($this->createStaff($role, ['email' => "{$role}@aisha.test"]))
                ->post("/tickets/{$this->ticket->id}/samples", ['decision' => 'disetujui'])->assertForbidden();
        }

        $otherMarketing = $this->createStaff('marketing', ['email' => 'marketing2@aisha.test']);
        $this->actingAs($otherMarketing)->post("/tickets/{$this->ticket->id}/samples", ['decision' => 'disetujui'])->assertForbidden();

        $this->ticket->update(['status' => TicketStatus::Rejected]);
        $this->actingAs($this->marketing)->post("/tickets/{$this->ticket->id}/samples", ['decision' => 'disetujui'])->assertForbidden();
        $this->assertSame(0, SampleApproval::query()->count());
    }

    public function test_the_samples_page_lists_active_initial_tickets_of_the_marketing_staff_with_the_latest_decision(): void
    {
        SampleApproval::factory()->create(['ticket_id' => $this->ticket->id, 'recorded_by' => $this->marketing->id]);
        SampleApproval::factory()->approved()->create(['ticket_id' => $this->ticket->id, 'revision' => 2, 'recorded_by' => $this->marketing->id]);
        Ticket::factory()->create(['client_id' => $this->ticket->client_id, 'phase' => StagePhase::PreRegistration]);
        Ticket::factory()->create(['client_id' => $this->ticket->client_id, 'status' => TicketStatus::Completed]);
        Ticket::factory()->create();

        $this->actingAs($this->marketing)->withoutVite()->get('/samples')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Backend/Samples', false)
                ->has('tickets.data', 1)
                ->where('tickets.data.0.code', $this->ticket->code)
                ->where('tickets.data.0.revisions', 2)
                ->where('tickets.data.0.decision', 'disetujui')
                ->where('navigation', fn ($items): bool => collect($items)->contains(fn (array $item): bool => $item['id'] === 'samples' && $item['available'] === true)));

        $this->actingAs($this->createStaff('ra', ['email' => 'ra@aisha.test']))->get('/samples')->assertForbidden();
    }

    public function test_the_ticket_page_shows_the_sample_history_and_hides_recording_after_approval(): void
    {
        SampleApproval::factory()->create(['ticket_id' => $this->ticket->id, 'recorded_by' => $this->marketing->id, 'note' => 'Rasa terlalu pahit.']);

        $this->actingAs($this->marketing)->withoutVite()->get("/tickets/{$this->ticket->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('samples.0.revision', 1)
                ->where('samples.0.note', 'Rasa terlalu pahit.')
                ->where('samples.0.recorded_by', $this->marketing->name)
                ->where('can.record_sample', true)
                ->where('urls.samples', "/tickets/{$this->ticket->id}/samples"));

        $this->post("/tickets/{$this->ticket->id}/samples", ['decision' => 'disetujui'])->assertSessionHasNoErrors();
        $this->get("/tickets/{$this->ticket->id}")
            ->assertInertia(fn (Assert $page) => $page->where('can.record_sample', false)->where('samples.0.decision', 'disetujui')->where('history.0.label', 'Keputusan sampel dicatat'));
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
