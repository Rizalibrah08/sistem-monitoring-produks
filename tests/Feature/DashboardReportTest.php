<?php

namespace Tests\Feature;

use App\ContractStatus;
use App\DocumentRequirement;
use App\DocumentStatus;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Invoice;
use App\Models\Stage;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\User;
use App\StagePhase;
use App\SubmissionStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 10:00:00');
        $this->seed();
    }

    public function test_the_manager_dashboard_counts_active_late_waiting_and_review_tickets_per_phase_and_stage(): void
    {
        $late = $this->ticketIn('dokumen_pra_registrasi');
        $late->stageLogs()->sole()->update(['overdue_at' => now(), 'delay_cause' => 'staf']);
        $this->ticketIn('menunggu_bpom_pra_registrasi');
        $reviewed = $this->ticketIn('dokumen_pra_registrasi');
        $document = Document::factory()->create(['ticket_id' => $reviewed->id]);
        $reviewed->reviews()->create(['document_id' => $document->id, 'document_version_id' => $this->version($document)->id, 'trigger' => 'versi_baru', 'message' => 'x']);

        $this->actingAs($this->staff('manager'))->withoutVite()->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats', fn ($stats): bool => collect($stats)->pluck('value', 'key')->all() === ['active' => 3, 'late' => 1, 'waiting_bpom' => 1, 'review' => 1])
                ->where('breakdown', fn ($rows): bool => collect($rows)->firstWhere('stage', 'Dokumen pra-registrasi')['count'] === 2));
    }

    public function test_staff_see_their_own_document_tasks_on_the_dashboard_and_task_page(): void
    {
        $ticket = $this->ticketIn('dokumen_pra_registrasi');
        $this->item($ticket, 'Master formula', 'rnd', DocumentStatus::Missing);
        $this->item($ticket, 'CoA produk jadi', 'qa', DocumentStatus::UnderReview, verifier: 'qa', uploaded: true);
        $approval = $this->item($ticket, 'Surat pernyataan', 'marketing', DocumentStatus::UnderReview, uploaded: true);
        $submission = $ticket->bpomSubmissions()->create(['phase' => StagePhase::PreRegistration, 'submitted_on' => '2026-10-01', 'pnbp' => 1, 'status' => SubmissionStatus::AwaitingResult, 'recorded_by' => User::factory()->create()->id]);
        $submission->requests()->create(['ticket_id' => $ticket->id, 'sequence' => 1, 'note' => 'Lengkapi data.', 'requested_on' => '2026-10-20', 'deadline' => '2026-12-01', 'recorded_by' => $submission->recorded_by]);

        $this->actingAs($this->staff('rnd'))->withoutVite()->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->has('tasks', 1)->where('tasks.0.title', 'Unggah Master formula')->where('tasks.0.url', "/tickets/{$ticket->id}"));
        $this->get('/tasks')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Backend/Tasks', false)->has('tasks', 1));

        $this->actingAs($this->staff('qa'))->get('/tasks')->assertInertia(fn (Assert $page) => $page->has('tasks', 1)->where('tasks.0.title', 'Verifikasi isi CoA produk jadi'));
        $this->actingAs($this->staff('ra'))->get('/tasks')->assertInertia(fn (Assert $page) => $page->has('tasks', 2)
            ->where('tasks.0.title', 'Tanggapi tambahan data TD1')->where('tasks.0.due', '01/12/2026')
            ->where('tasks.1.title', "Setujui pemakaian {$approval->documentType->name}"));
    }

    public function test_marketing_and_finance_dashboards_show_their_own_figures(): void
    {
        $marketing = $this->staff('marketing');
        $client = Client::factory()->create(['marketing_user_id' => $marketing->id]);
        $signed = $this->ticketIn('kontrak', $client);
        Contract::factory()->status(ContractStatus::Signed)->create(['ticket_id' => $signed->id, 'number' => 'KTR/2026/0009']);
        $waiting = $this->ticketIn('dokumen_pra_registrasi', $client);
        $this->item($waiting, 'Surat kuasa', 'client', DocumentStatus::Missing);
        $this->ticketIn('pengembangan_sampel', $client, StagePhase::Initial);

        $this->actingAs($marketing)->withoutVite()->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('stats', fn ($stats): bool => collect($stats)->pluck('value', 'key')->all() === ['prospects' => null, 'messages' => null, 'contracts' => 1, 'waiting_client' => 1])
            ->where('tasks', fn ($tasks): bool => collect($tasks)->pluck('title')->sort()->values()->all() === ['Catat keputusan sampel', 'Konfirmasi kontrak KTR/2026/0009', 'Unggah Surat kuasa']));

        Invoice::factory()->create(['amount' => 15_000_000, 'due_date' => '2026-11-01']);
        Invoice::factory()->paid()->create(['amount' => 5_000_000]);
        $this->actingAs($this->staff('finance'))->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('stats', fn ($stats): bool => collect($stats)->pluck('value', 'key')->all() === ['outstanding' => 'Rp15.000.000', 'unpaid' => 1, 'paid_month' => 1, 'overdue' => 1])
            ->where('tasks', []));
    }

    public function test_the_duration_report_gives_average_and_p75_per_stage_with_time_split_by_cause_and_exports_it(): void
    {
        $contract = Stage::query()->where('code', 'kontrak')->sole();
        $waiting = Stage::query()->where('code', 'menunggu_bpom_pra_registrasi')->sole();
        $ticket = Ticket::factory()->create();
        $ticket->stageLogs()->create(['stage_id' => $contract->id, 'started_at' => '2026-10-05 08:00:00', 'finished_at' => '2026-10-06 08:00:00']);
        $ticket->stageLogs()->create(['stage_id' => $contract->id, 'started_at' => '2026-10-12 08:00:00', 'finished_at' => '2026-10-15 08:00:00', 'delay_cause' => 'klien']);
        $ticket->stageLogs()->create(['stage_id' => $waiting->id, 'started_at' => '2026-10-15 08:00:00', 'finished_at' => '2026-10-22 08:00:00']);
        $ticket->stageLogs()->create(['stage_id' => $contract->id, 'started_at' => '2026-06-01 08:00:00', 'finished_at' => '2026-06-02 08:00:00']);

        $this->actingAs($this->staff('manager'))->withoutVite()->get('/reports?from=2026-10-01&until=2026-11-05')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Backend/Reports', false)
                ->has('stages', 2)
                ->where('stages.0.stage', 'Kontrak (MoU)')->where('stages.0.count', 2)->where('stages.0.average', 2)->where('stages.0.p75', 3)
                ->where('stages.0.internal', 1)->where('stages.0.client', 3)
                ->where('stages.1.external', 5)
                ->where('totals', ['internal' => 1, 'client' => 3, 'external' => 5]));

        $csv = $this->get('/reports/export?from=2026-10-01&until=2026-11-05')->assertOk()->assertDownload('laporan-durasi-20261001-20261105.csv')->streamedContent();
        $this->assertStringContainsString('"Kontrak (MoU)",1,2,2,3,1,3,0', $csv);
    }

    public function test_the_escalation_list_shows_overdue_stages_and_only_managers_open_reports(): void
    {
        $late = $this->ticketIn('dokumen_pra_registrasi');
        $late->stageLogs()->sole()->update(['overdue_at' => now(), 'delay_cause' => 'staf']);

        $this->actingAs($this->staff('manager'))->withoutVite()->get('/escalations')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Backend/Escalations', false)->has('escalations', 1)->where('escalations.0.by_client', false));

        $this->actingAs($this->staff('finance'))->get('/reports')->assertForbidden();
        $this->get('/escalations')->assertForbidden();
    }

    private function ticketIn(string $code, ?Client $client = null, StagePhase $phase = StagePhase::PreRegistration): Ticket
    {
        $stage = Stage::query()->where('code', $code)->sole();
        $ticket = Ticket::factory()->create(['phase' => $phase, 'current_stage_id' => $stage->id, ...($client ? ['client_id' => $client->id] : [])]);
        $ticket->stageLogs()->create(['stage_id' => $stage->id, 'started_at' => now()]);

        return $ticket;
    }

    private function item(Ticket $ticket, string $name, string $pic, DocumentStatus $status, ?string $verifier = null, bool $uploaded = false): TicketDocument
    {
        $type = DocumentType::factory()->create(['name' => $name, 'pic_role' => $pic, 'verifier_role' => $verifier, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required]);
        $document = null;

        if ($uploaded) {
            $document = Document::factory()->create(['document_type_id' => $type->id, 'ticket_id' => $ticket->id]);
            $document->update(['current_version_id' => $this->version($document)->id]);
        }

        return $ticket->documents()->create(['document_type_id' => $type->id, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required, 'document_id' => $document?->id, 'status' => $status]);
    }

    private function version(Document $document): DocumentVersion
    {
        return $document->versions()->create([
            'version' => 1, 'path' => 'documents/v1.pdf', 'original_name' => 'v1.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
            'checksum' => str_repeat('a', 64), 'number' => 'DOC-1', 'source' => 'internal', 'validation_passed' => true, 'validation_errors' => [],
        ]);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['email' => "{$role}@aisha.test"]);
        $user->assignRole($role);

        return $user;
    }
}
