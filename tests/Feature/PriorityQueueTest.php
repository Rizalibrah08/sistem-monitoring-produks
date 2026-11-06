<?php

namespace Tests\Feature;

use App\ContractStatus;
use App\CriterionType;
use App\DocumentRequirement;
use App\DocumentStatus;
use App\Models\Client;
use App\Models\Contract;
use App\Models\DocumentType;
use App\Models\PriorityScore;
use App\Models\Stage;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PriorityQueue;
use App\StagePhase;
use App\SubmissionStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PriorityQueueTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 10:00:00');
        $this->seed();
    }

    public function test_topsis_ranks_the_dominant_alternative_first_and_handles_degenerate_input(): void
    {
        $queue = app(PriorityQueue::class);
        $scores = $queue->topsis([[1, 10], [2, 5], [3, 1]], [0.5, 0.5], [CriterionType::Benefit, CriterionType::Cost]);

        $this->assertSame(1.0, $scores[2]);
        $this->assertSame(0.0, $scores[0]);
        $this->assertGreaterThan($scores[0], $scores[1]);
        $this->assertLessThan($scores[2], $scores[1]);
        $this->assertSame([1.0], $queue->topsis([[5, 5]], [0.5, 0.5], [CriterionType::Benefit, CriterionType::Cost]));
        $this->assertSame([1.0, 0.0], $queue->topsis([[0, 2], [0, 1]], [0.5, 0.5], [CriterionType::Benefit, CriterionType::Benefit]));
        $this->assertSame([], $queue->topsis([], [0.5], [CriterionType::Cost]));
    }

    public function test_the_regulatory_queue_skips_tickets_waiting_for_bpom_or_without_an_active_contract_and_puts_urgent_ones_first(): void
    {
        $overdue = $this->ticket('dokumen_pra_registrasi', dueAt: '2026-11-04 10:00:00');
        $calm = $this->ticket('dokumen_pra_registrasi', dueAt: '2026-11-20 10:00:00');
        $deadline = $this->ticket('dokumen_registrasi', dueAt: '2026-12-20 10:00:00', phase: StagePhase::Registration);
        $deadline->bpomSubmissions()->create(['phase' => StagePhase::PreRegistration, 'submitted_on' => '2026-09-01', 'pnbp' => 1, 'status' => SubmissionStatus::ResultIssued, 'result_on' => '2026-10-01', 'registration_deadline' => '2026-11-09', 'recorded_by' => User::factory()->create()->id]);
        $this->ticket('menunggu_bpom_pra_registrasi');
        $this->ticket('dokumen_pra_registrasi', contract: false);

        $scores = app(PriorityQueue::class)->compute('ra', now());

        $this->assertSame([$deadline->id, $overdue->id, $calm->id], $scores->pluck('ticket_id')->all());
        $this->assertSame([1, 2, 3], $scores->pluck('rank')->all());
        $byTicket = $scores->keyBy('ticket_id');
        $this->assertLessThan(0, $byTicket[$overdue->id]->c1);
        $this->assertSame(2.0, $byTicket[$deadline->id]->c2);
        $this->assertSame((float) PriorityQueue::NO_BPOM_DEADLINE, $byTicket[$calm->id]->c2);
        $this->assertSame(3, PriorityScore::query()->where('queue', 'ra')->count());
    }

    public function test_research_and_quality_queues_only_hold_tickets_with_work_for_that_role(): void
    {
        $withFormula = $this->ticket('dokumen_pra_registrasi', pending: ['rnd' => DocumentStatus::Missing]);
        $withCoa = $this->ticket('dokumen_pra_registrasi', pending: ['qa' => DocumentStatus::UnderReview], verifier: 'qa');
        $this->ticket('dokumen_pra_registrasi', pending: ['ra' => DocumentStatus::Missing]);

        $this->assertSame([$withFormula->id], app(PriorityQueue::class)->compute('rnd', now())->pluck('ticket_id')->all());
        $this->assertSame([$withCoa->id], app(PriorityQueue::class)->compute('qa', now())->pluck('ticket_id')->all());
    }

    public function test_the_queue_page_recomputes_on_open_shows_criteria_and_respects_visibility(): void
    {
        $marketing = $this->staff('marketing');
        $own = $this->ticket('dokumen_pra_registrasi', dueAt: '2026-11-04 10:00:00', client: Client::factory()->create(['marketing_user_id' => $marketing->id]));
        $this->ticket('dokumen_pra_registrasi');

        $this->actingAs($marketing)->withoutVite()->get('/priorities')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Backend/Priorities', false)
                ->where('queue', 'marketing')->where('queues', null)->has('criteria', 4)
                ->has('rows', 1)->where('rows.0.rank', 1)->where('rows.0.ticket_id', $own->id)
                ->where('rows.0.reason', 'Tahap berjalan sudah melewati tenggat.')
                ->where('navigation', fn ($items): bool => collect($items)->contains(fn (array $item): bool => $item['id'] === 'priorities' && $item['available'] === true)));

        $manager = $this->staff('manager');
        $this->actingAs($manager)->get('/priorities?queue=marketing')->assertInertia(fn (Assert $page) => $page->where('queue', 'marketing')->has('rows', 2)->has('queues', 4));
        $this->actingAs($this->staff('finance'))->get('/priorities')->assertForbidden();
    }

    public function test_the_hourly_command_computes_every_queue(): void
    {
        $this->ticket('dokumen_pra_registrasi');

        $this->artisan('priorities:compute')->expectsOutput('Antrean ra: 1 tiket.')->expectsOutput('Antrean marketing: 1 tiket.')->assertSuccessful();
    }

    /** @param array<string, DocumentStatus> $pending */
    private function ticket(string $stageCode, ?string $dueAt = null, StagePhase $phase = StagePhase::PreRegistration, bool $contract = true, array $pending = [], ?string $verifier = null, ?Client $client = null): Ticket
    {
        $stage = Stage::query()->where('code', $stageCode)->sole();
        $ticket = Ticket::factory()->create(['phase' => $phase, 'current_stage_id' => $stage->id, 'created_at' => now()->subDays(10), ...($client ? ['client_id' => $client->id] : [])]);
        $ticket->stageLogs()->create(['stage_id' => $stage->id, 'started_at' => now()->subDays(2), ...($dueAt ? ['due_at' => $dueAt] : [])]);

        if ($contract) {
            Contract::factory()->status(ContractStatus::Active)->create(['ticket_id' => $ticket->id]);
        }

        foreach ($pending as $pic => $status) {
            $type = DocumentType::factory()->create(['pic_role' => $pic, 'verifier_role' => $verifier, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required]);
            $ticket->documents()->create(['document_type_id' => $type->id, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required, 'status' => $status]);
        }

        return $ticket;
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['email' => "{$role}@aisha.test"]);
        $user->assignRole($role);

        return $user;
    }
}
