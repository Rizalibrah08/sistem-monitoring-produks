<?php

namespace Tests\Feature;

use App\ContractStatus;
use App\InvoiceTerm;
use App\Models\Activity;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\LabTest;
use App\Models\Stage;
use App\Models\Ticket;
use App\Models\User;
use App\StagePhase;
use App\SubmissionStatus;
use App\TicketStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LabTestAndNieTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $regulatory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-05 10:00:00');
        $this->regulatory = $this->createStaff('ra');
    }

    public function test_lab_tests_run_alongside_pre_registration_with_reanalysis_until_a_conforming_report(): void
    {
        $ticket = $this->ticketIn(StagePhase::PreRegistration, 'dokumen_pra_registrasi');
        $research = $this->createStaff('rnd');

        $this->actingAs($research)->post("/tickets/{$ticket->id}/lab-tests", $this->shipment())->assertSessionHas('status', 'Pengiriman sampel ke laboratorium dicatat.');
        $this->from("/tickets/{$ticket->id}")->post("/tickets/{$ticket->id}/lab-tests", $this->shipment())
            ->assertSessionHasErrors(['laboratory' => 'Uji laboratorium sebelumnya masih berjalan atau sudah sesuai spesifikasi.']);

        $ticket->refresh();
        $this->assertSame('dokumen_pra_registrasi', $ticket->currentStage->code);
        $labLog = $ticket->stageLogs()->whereHas('stage', fn ($query) => $query->where('code', 'uji_laboratorium'))->sole();
        $this->assertNull($labLog->finished_at);

        $first = LabTest::query()->sole();
        $this->post("/lab-tests/{$first->id}/result", $this->report(['conforms' => '0']))->assertSessionHasErrors(['note' => 'Jelaskan parameter yang tidak sesuai spesifikasi.']);
        $this->post("/lab-tests/{$first->id}/result", $this->report(['conforms' => '0', 'note' => 'Cemaran mikroba melebihi batas.']))->assertSessionHasNoErrors();
        $this->assertFalse($first->refresh()->conforms);
        $this->assertNull($labLog->refresh()->finished_at);

        $this->post("/tickets/{$ticket->id}/lab-tests", $this->shipment(['sent_on' => '2026-11-05', 'estimated_on' => '2026-11-19']))
            ->assertSessionHas('status', 'Analisis ulang dicatat; tahap uji laboratorium diperpanjang.');
        $second = LabTest::query()->where('attempt', 2)->sole();
        $this->assertDatabaseHas('activity_log', ['event' => 'lab_test.reanalysis', 'ticket_code' => $ticket->code]);
        $this->assertSame(1, $ticket->stageLogs()->whereHas('stage', fn ($query) => $query->where('code', 'uji_laboratorium'))->count());

        $this->post("/lab-tests/{$second->id}/result", $this->report(['result_on' => '2026-11-05']))->assertSessionHas('status', 'LHU sesuai spesifikasi tercatat.');
        $this->assertNotNull($labLog->refresh()->finished_at);
        $this->get("/lab-tests/{$second->id}/file")->assertOk()->assertDownload('lhu.pdf');

        $this->actingAs($this->regulatory)->get("/tickets/{$ticket->id}")
            ->assertInertia(fn (Assert $page) => $page->has('labTests.tests', 2)->where('labTests.tests.1.conforms', true)
                ->where('bpom.registration_blockers', fn ($blockers): bool => ! collect($blockers)->contains('LHU uji laboratorium yang sesuai spesifikasi belum diunggah.')));
    }

    public function test_packaging_design_and_the_nie_complete_the_ticket_and_issue_the_settlement_invoice(): void
    {
        $ticket = $this->ticketIn(StagePhase::Registration, 'menunggu_bpom_registrasi');
        $contract = Contract::factory()->status(ContractStatus::Active)->create(['ticket_id' => $ticket->id, 'service_value' => 30_000_000, 'dp_percent' => 50]);
        $submission = $ticket->bpomSubmissions()->create(['phase' => StagePhase::Registration, 'submitted_on' => '2026-09-01', 'pnbp' => 3_000_000, 'status' => SubmissionStatus::AwaitingResult, 'recorded_by' => $this->regulatory->id]);

        $this->actingAs($this->regulatory)->from("/tickets/{$ticket->id}")->post("/tickets/{$ticket->id}/packaging", ['action' => 'uploaded', 'date' => '2026-11-03'])
            ->assertSessionHasErrors(['uploaded_on' => 'Catat permintaan desain kemasan terlebih dahulu.']);
        $this->post("/tickets/{$ticket->id}/packaging", ['action' => 'requested', 'date' => '2026-11-01'])->assertSessionHasNoErrors();
        $this->assertSame('unggah_desain_kemasan', $ticket->refresh()->currentStage->code);
        $this->get('/nie')->assertInertia(fn (Assert $page) => $page->component('Backend/Nie', false)->has('tickets', 1)->where('tickets.0.packaging_requested_on', '01/11/2026'));
        $this->post("/tickets/{$ticket->id}/packaging", ['action' => 'uploaded', 'date' => '2026-11-03'])->assertSessionHasNoErrors();

        $this->post("/tickets/{$ticket->id}/nie", ['nie_number' => 'TR263012345', 'nie_issued_on' => '2026-11-05'])
            ->assertSessionHas('status', 'NIE TR263012345 tercatat; tiket selesai dan tagihan pelunasan diterbitkan.');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Completed, $ticket->status);
        $this->assertSame('TR263012345', $ticket->nie_number);
        $this->assertSame('2026-11-05', $ticket->nie_issued_on->toDateString());
        $this->assertSame(0, $ticket->stageLogs()->whereNull('finished_at')->count());
        $this->assertSame(SubmissionStatus::ResultIssued, $submission->refresh()->status);
        $settlement = Invoice::query()->where('contract_id', $contract->id)->where('term', InvoiceTerm::Settlement)->sole();
        $this->assertSame(15_000_000, $settlement->amount);
        $this->assertSame($settlement->number, Activity::query()->where('event', 'ticket.nie_recorded')->sole()->attribute_changes['attributes']['settlement_invoice']);
        $this->get('/nie')->assertInertia(fn (Assert $page) => $page->has('tickets', 0));

        $other = $this->ticketIn(StagePhase::Registration, 'menunggu_bpom_registrasi');
        $this->post("/tickets/{$other->id}/nie", ['nie_number' => 'TR263012345', 'nie_issued_on' => '2026-11-05'])
            ->assertSessionHasErrors(['nie_number' => 'Nomor NIE ini sudah tercatat pada tiket lain.']);
        $this->post("/tickets/{$other->id}/nie", ['nie_number' => 'TR263099999', 'nie_issued_on' => '2026-11-05'])
            ->assertSessionHasErrors(['nie_number' => 'Tiket belum memiliki pengajuan registrasi yang menunggu hasil BPOM.']);
    }

    public function test_only_research_and_regulatory_affairs_record_lab_tests_and_only_regulatory_affairs_records_the_nie(): void
    {
        $ticket = $this->ticketIn(StagePhase::PreRegistration, 'dokumen_pra_registrasi');

        foreach (['marketing', 'qa'] as $role) {
            $this->actingAs($this->createStaff($role))->post("/tickets/{$ticket->id}/lab-tests", $this->shipment())->assertForbidden();
        }

        $this->actingAs($this->createStaff('rnd'))->post("/tickets/{$ticket->id}/nie", ['nie_number' => 'X', 'nie_issued_on' => '2026-11-05'])->assertForbidden();
        $this->assertSame(0, LabTest::query()->count());
    }

    private function ticketIn(StagePhase $phase, string $stageCode): Ticket
    {
        $stage = Stage::query()->where('code', $stageCode)->sole();
        $ticket = Ticket::factory()->create(['phase' => $phase, 'current_stage_id' => $stage->id]);
        $ticket->stageLogs()->create(['stage_id' => $stage->id, 'started_at' => now()->subDays(2)]);

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function shipment(array $overrides = []): array
    {
        return ['laboratory' => 'Lab Uji Nusantara', 'sent_on' => '2026-10-20', 'estimated_on' => '2026-11-03', ...$overrides];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function report(array $overrides = []): array
    {
        return ['result_on' => '2026-11-03', 'conforms' => '1', 'note' => '', 'file' => UploadedFile::fake()->create('lhu.pdf', 100, 'application/pdf'), ...$overrides];
    }

    private function createStaff(string $role): User
    {
        $this->seed();
        $user = User::factory()->create(['email' => "{$role}@aisha.test"]);
        $user->assignRole($role);

        return $user;
    }
}
