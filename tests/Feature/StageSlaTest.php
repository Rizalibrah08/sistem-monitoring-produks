<?php

namespace Tests\Feature;

use App\ContractStatus;
use App\DocumentRequirement;
use App\DocumentStatus;
use App\Models\Contract;
use App\Models\DocumentType;
use App\Models\Holiday;
use App\Models\Stage;
use App\Models\Ticket;
use App\Models\User;
use App\Services\WorkingDays;
use App\StagePhase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StageSlaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 10:00:00');
        $this->seed();
    }

    public function test_deadlines_count_working_hours_from_eight_to_four_without_weekends_or_holidays(): void
    {
        $calendar = app(WorkingDays::class);
        Holiday::factory()->create(['date' => '2026-11-10', 'name' => 'Hari Pahlawan']);

        $this->assertSame('2026-11-06 10:00', $calendar->dueAt(Carbon::parse('2026-11-05 10:00'), 1)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-26 10:00', $calendar->dueAt(Carbon::parse('2026-11-05 10:00'), 14)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-09 16:00', $calendar->dueAt(Carbon::parse('2026-11-06 17:30'), 1)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-09 16:00', $calendar->dueAt(Carbon::parse('2026-11-07 09:00'), 1)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-11 16:00', $calendar->dueAt(Carbon::parse('2026-11-09 07:00'), 2)->format('Y-m-d H:i'));
        $this->assertSame(120, $calendar->workingMinutesBetween(Carbon::parse('2026-11-06 15:00'), Carbon::parse('2026-11-09 09:00')));
        $this->assertSame(0, $calendar->workingMinutesBetween(Carbon::parse('2026-11-10 09:00'), Carbon::parse('2026-11-10 15:00')));
    }

    public function test_stage_logs_get_their_deadline_from_the_stage_sla_except_while_waiting_for_external_parties(): void
    {
        $ticket = Ticket::factory()->create();
        $sample = $ticket->stageLogs()->create(['stage_id' => $this->stage('pengembangan_sampel')->id, 'started_at' => now()]);
        $waiting = $ticket->stageLogs()->create(['stage_id' => $this->stage('menunggu_bpom_pra_registrasi')->id, 'started_at' => now()]);

        $this->assertSame('2026-11-25 10:00', $sample->due_at->format('Y-m-d H:i'));
        $this->assertNull($waiting->due_at);
    }

    public function test_overdue_stages_are_flagged_hourly_with_a_client_or_staff_cause(): void
    {
        $contractTicket = $this->ticketInStage('kontrak');
        Contract::factory()->status(ContractStatus::Sent)->create(['ticket_id' => $contractTicket->id]);
        $clientDocuments = $this->ticketInStage('dokumen_pra_registrasi', ['client']);
        $staffDocuments = $this->ticketInStage('dokumen_pra_registrasi', ['client', 'rnd']);
        $onTime = $this->ticketInStage('pengembangan_sampel', dueSoon: false);

        $this->travelTo('2026-11-06 11:00:00');
        $this->artisan('sla:check')->expectsOutput('3 tahap ditandai terlambat.')->assertSuccessful();
        $this->artisan('sla:check')->expectsOutput('0 tahap ditandai terlambat.');

        $this->assertSame('klien', $contractTicket->stageLogs()->sole()->delay_cause);
        $this->assertSame('klien', $clientDocuments->stageLogs()->sole()->delay_cause);
        $this->assertSame('staf', $staffDocuments->stageLogs()->sole()->delay_cause);
        $this->assertNull($onTime->stageLogs()->sole()->overdue_at);
        $this->assertDatabaseHas('activity_log', ['event' => 'stage.overdue', 'ticket_code' => $staffDocuments->code, 'source' => 'system']);

        $user = User::factory()->create();
        $user->assignRole('ra');
        $this->actingAs($user)->withoutVite()->get('/tickets')->assertInertia(fn (Assert $page) => $page
            ->where('tickets.data', fn ($rows): bool => collect($rows)->firstWhere('id', $staffDocuments->id)['is_late'] === true
                && collect($rows)->firstWhere('id', $onTime->id)['is_late'] === false));
        $this->get("/tickets/{$clientDocuments->id}")->assertInertia(fn (Assert $page) => $page->where('stages.0.overdue', true)->where('stages.0.delay_cause', 'klien'));
    }

    /** @param list<string> $pendingPics */
    private function ticketInStage(string $code, array $pendingPics = [], bool $dueSoon = true): Ticket
    {
        $stage = $this->stage($code);
        $ticket = Ticket::factory()->create(['phase' => $stage->phase === StagePhase::BothPhases ? StagePhase::PreRegistration : $stage->phase, 'current_stage_id' => $stage->id]);
        $ticket->stageLogs()->create(['stage_id' => $stage->id, 'started_at' => now(), 'due_at' => $dueSoon ? now()->addHour() : null]);

        foreach ($pendingPics as $pic) {
            $type = DocumentType::factory()->create(['pic_role' => $pic, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required]);
            $ticket->documents()->create(['document_type_id' => $type->id, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required, 'status' => DocumentStatus::Missing]);
        }

        return $ticket;
    }

    private function stage(string $code): Stage
    {
        return Stage::query()->where('code', $code)->sole();
    }
}
