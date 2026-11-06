<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentStatus;
use App\Models\BpomSubmission;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Holiday;
use App\Models\Stage;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\User;
use App\StagePhase;
use App\SubmissionStatus;
use App\TicketStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BpomWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $regulatory;

    private Ticket $ticket;

    private TicketDocument $formula;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-05 10:00:00');
        $this->regulatory = $this->createStaff('ra');
        $stage = Stage::query()->where('code', 'dokumen_pra_registrasi')->sole();
        $this->ticket = Ticket::factory()->create(['phase' => StagePhase::PreRegistration, 'current_stage_id' => $stage->id]);
        $this->ticket->stageLogs()->create(['stage_id' => $stage->id, 'started_at' => now()->subDays(3)]);
        $this->formula = $this->item('Master formula', StagePhase::PreRegistration, DocumentStatus::Approved);
        $this->item('Surat kuasa', StagePhase::BothPhases, DocumentStatus::Approved);
        $this->item('Desain kemasan', StagePhase::Registration, DocumentStatus::Missing);
    }

    public function test_a_submission_needs_every_document_of_the_phase_approved_and_locks_their_versions(): void
    {
        $this->formula->update(['status' => DocumentStatus::UnderReview]);

        $this->actingAs($this->regulatory)->from("/tickets/{$this->ticket->id}")->post($this->submitUrl(), $this->submission())
            ->assertSessionHasErrors(['bpom' => 'Belum dapat diajukan: Dokumen belum disetujui: Master formula.']);

        $this->formula->update(['status' => DocumentStatus::Approved]);
        $this->post($this->submitUrl(), $this->submission())->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Pengajuan Pra-registrasi tercatat; versi dokumen yang diajukan dikunci.');

        $submission = BpomSubmission::query()->sole();
        $this->assertSame(SubmissionStatus::AwaitingResult, $submission->status);
        $this->assertSame(2_500_000, $submission->pnbp);
        $this->assertSame(2, $submission->lockedVersions()->count());
        $this->assertSame('menunggu_bpom_pra_registrasi', $this->ticket->refresh()->currentStage->code);
        $this->assertDatabaseHas('activity_log', ['event' => 'bpom.submitted', 'ticket_code' => $this->ticket->code]);

        $lockedId = $this->formula->document->current_version_id;
        $newVersion = $this->formula->document->versions()->create($this->versionAttributes(2));
        $this->formula->document->update(['current_version_id' => $newVersion->id]);
        $this->assertContains($lockedId, $submission->lockedVersions()->pluck('document_versions.id')->all());
        $this->assertNotContains($newVersion->id, $submission->lockedVersions()->pluck('document_versions.id')->all());

        $this->post($this->submitUrl(), $this->submission())->assertSessionHasErrors(['bpom' => 'Pengajuan Pra-registrasi sudah dicatat.']);
    }

    public function test_the_pre_registration_result_sets_a_thirty_working_day_deadline_and_the_gate_opens_registration(): void
    {
        Holiday::factory()->create(['date' => '2026-11-10', 'name' => 'Hari Pahlawan']);
        $this->actingAs($this->regulatory)->post($this->submitUrl(), $this->submission())->assertSessionHasNoErrors();
        $submission = BpomSubmission::query()->sole();

        $this->get("/tickets/{$this->ticket->id}")->assertInertia(fn (Assert $page) => $page->where('bpom.registration_blockers', ['Hasil pra-registrasi belum dicatat dan diunggah.']));
        $this->from("/tickets/{$this->ticket->id}")->post("/tickets/{$this->ticket->id}/registration")
            ->assertSessionHasErrors(['bpom' => 'Fase registrasi belum dapat dibuka: Hasil pra-registrasi belum dicatat dan diunggah.']);

        $this->post("/bpom-submissions/{$submission->id}/result", ['result_on' => '2026-11-05', 'file' => UploadedFile::fake()->create('hasil.pdf', 100, 'application/pdf')])
            ->assertSessionHas('status', 'Hasil pra-registrasi tercatat. Registrasi harus diajukan paling lambat 18/12/2026.');

        $submission->refresh();
        $this->assertSame(SubmissionStatus::ResultIssued, $submission->status);
        $this->get("/bpom-submissions/{$submission->id}/result")->assertOk()->assertDownload('hasil.pdf');
        $this->get("/tickets/{$this->ticket->id}")->assertInertia(fn (Assert $page) => $page->where('bpom.registration_blockers', []));

        $this->post("/tickets/{$this->ticket->id}/registration")->assertSessionHas('status', 'Fase registrasi dibuka.');
        $this->ticket->refresh();
        $this->assertSame(StagePhase::Registration, $this->ticket->phase);
        $this->assertSame('dokumen_registrasi', $this->ticket->currentStage->code);
    }

    public function test_additional_data_returns_documents_with_a_sixty_working_day_deadline_up_to_td3_then_allows_rejection(): void
    {
        $this->actingAs($this->regulatory)->post($this->submitUrl(), $this->submission())->assertSessionHasNoErrors();
        $submission = BpomSubmission::query()->sole();
        $this->from("/tickets/{$this->ticket->id}")->post("/tickets/{$this->ticket->id}/rejection", ['reason' => 'Ditolak.'])
            ->assertSessionHasErrors(['reason' => 'Status ditolak hanya dapat dicatat setelah tambahan data ketiga (TD3).']);

        $this->post("/bpom-submissions/{$submission->id}/requests", ['note' => 'Lengkapi uji stabilitas.', 'requested_on' => '2026-11-05', 'item_ids' => [$this->formula->id]])
            ->assertSessionHas('status', 'Tambahan data TD1 tercatat dengan tenggat 28/01/2027.');

        $this->formula->refresh();
        $this->assertSame(DocumentStatus::NeedsRevision, $this->formula->status);
        $this->assertSame('Tambahan data BPOM TD1: Lengkapi uji stabilitas.', $this->formula->revision_reason);
        $this->assertTrue($this->ticket->stageLogs()->whereHas('stage', fn ($query) => $query->where('code', 'perbaikan_dokumen'))->whereNull('finished_at')->exists());

        $first = $submission->requests()->sole();
        $this->post("/bpom-requests/{$first->id}/respond")->assertSessionHasNoErrors();
        $this->assertNotNull($first->refresh()->responded_at);

        $this->post("/bpom-submissions/{$submission->id}/requests", ['note' => 'TD2', 'requested_on' => '2026-11-05'])->assertSessionHasNoErrors();
        $this->post("/bpom-submissions/{$submission->id}/requests", ['note' => 'TD3', 'requested_on' => '2026-11-05'])->assertSessionHasNoErrors();
        $this->post("/bpom-submissions/{$submission->id}/requests", ['note' => 'TD4', 'requested_on' => '2026-11-05'])
            ->assertSessionHasErrors(['note' => 'Tambahan data hanya dapat dicatat paling banyak tiga kali selama menunggu hasil BPOM.']);
        $this->get('/bpom')->assertInertia(fn (Assert $page) => $page->component('Backend/Bpom', false)->where('submissions.0.rejection_risk', true)->where('submissions.0.status_label', 'Tambahan data TD2'));

        $this->post("/tickets/{$this->ticket->id}/rejection", ['reason' => 'TD3 tidak terpenuhi.'])->assertSessionHas('status');
        $this->assertSame(TicketStatus::Rejected, $this->ticket->refresh()->status);
        $this->assertSame(0, $this->ticket->stageLogs()->whereNull('finished_at')->count());
    }

    public function test_an_unsubmitted_registration_expires_after_its_deadline_and_the_ticket_returns_to_pre_registration(): void
    {
        $this->actingAs($this->regulatory)->post($this->submitUrl(), $this->submission())->assertSessionHasNoErrors();
        $submission = BpomSubmission::query()->sole();
        $this->post("/bpom-submissions/{$submission->id}/result", ['result_on' => '2026-11-05', 'file' => UploadedFile::fake()->create('hasil.pdf', 100, 'application/pdf')]);
        $this->post("/tickets/{$this->ticket->id}/registration")->assertSessionHasNoErrors();

        $this->travelTo('2026-12-17 10:00:00');
        $this->artisan('bpom:expire-pre-registrations')->expectsOutput('0 pra-registrasi ditandai hangus.');
        $this->travelTo('2026-12-18 10:00:00');
        $this->flushSession()->actingAs($this->regulatory)->from("/tickets/{$this->ticket->id}")->post($this->submitUrl(), $this->submission(['submitted_on' => '2026-12-18']))
            ->assertSessionHasErrors('bpom');
        $this->artisan('bpom:expire-pre-registrations')->expectsOutput('1 pra-registrasi ditandai hangus.');

        $this->assertSame(SubmissionStatus::Expired, $submission->refresh()->status);
        $this->ticket->refresh();
        $this->assertSame(StagePhase::PreRegistration, $this->ticket->phase);
        $this->assertSame('dokumen_pra_registrasi', $this->ticket->currentStage->code);
        $this->post($this->submitUrl(), $this->submission(['submitted_on' => '2026-12-18']))->assertSessionHasNoErrors();
        $this->assertSame(2, $this->ticket->bpomSubmissions()->count());
    }

    public function test_only_regulatory_affairs_records_bpom_progress(): void
    {
        $marketing = $this->createStaff('marketing');

        $this->actingAs($marketing)->post($this->submitUrl(), $this->submission())->assertForbidden();
        $this->get('/bpom')->assertForbidden();
        $this->assertSame(0, BpomSubmission::query()->count());
    }

    private function item(string $name, StagePhase $phase, DocumentStatus $status): TicketDocument
    {
        $type = DocumentType::factory()->create(['name' => $name, 'phase' => $phase, 'scope' => DocumentScope::Ticket, 'requirement' => DocumentRequirement::Required, 'pic_role' => 'rnd']);
        $document = null;

        if ($status !== DocumentStatus::Missing) {
            $document = Document::factory()->create(['document_type_id' => $type->id, 'ticket_id' => $this->ticket->id]);
            $version = $document->versions()->create($this->versionAttributes(1));
            $document->update(['current_version_id' => $version->id]);
        }

        return $this->ticket->documents()->create(['document_type_id' => $type->id, 'phase' => $phase, 'requirement' => $type->requirement, 'document_id' => $document?->id, 'status' => $status]);
    }

    /** @return array<string, mixed> */
    private function versionAttributes(int $version): array
    {
        return [
            'version' => $version, 'path' => "documents/v{$version}.pdf", 'original_name' => "v{$version}.pdf", 'mime_type' => 'application/pdf', 'size' => 100,
            'checksum' => str_repeat('a', 64), 'number' => "DOC-{$version}", 'source' => 'internal', 'validation_passed' => true, 'validation_errors' => [],
        ];
    }

    private function submitUrl(): string
    {
        return "/tickets/{$this->ticket->id}/bpom-submissions";
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function submission(array $overrides = []): array
    {
        return ['submitted_on' => '2026-11-05', 'pnbp' => 2_500_000, ...$overrides];
    }

    private function createStaff(string $role): User
    {
        $this->seed();
        $user = User::factory()->create(['email' => "{$role}@aisha.test"]);
        $user->assignRole($role);

        return $user;
    }
}
