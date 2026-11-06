<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentStatus;
use App\Models\BpomRequest;
use App\Models\Client;
use App\Models\DocumentType;
use App\Models\Stage;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\StaffNotifier;
use App\StagePhase;
use App\SubmissionStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $marketing;

    private User $regulatory;

    private User $research;

    private User $manager;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-05 10:00:00');
        $this->seed();
        $this->marketing = $this->staff('marketing');
        $this->regulatory = $this->staff('ra');
        $this->research = $this->staff('rnd');
        $this->manager = $this->staff('manager');
        $this->ticket = Ticket::factory()->create(['client_id' => Client::factory()->create(['marketing_user_id' => $this->marketing->id])->id]);
    }

    public function test_returned_documents_notify_their_person_in_charge_and_valid_uploads_notify_the_verifier(): void
    {
        $formula = $this->item('Master formula', 'rnd');

        $this->actingAs($this->research)->post("/tickets/{$this->ticket->id}/documents/{$formula->id}/versions", [
            'file' => UploadedFile::fake()->create('formula.pdf', 100, 'application/pdf'), 'number' => 'MF-1', 'issued_at' => '2026-11-01',
        ])->assertSessionHasNoErrors();

        $awaiting = $this->regulatory->notifications()->sole();
        $this->assertSame('dokumen_menunggu_verifikasi', $awaiting->data['kind']);
        $this->assertSame('/verification', $awaiting->data['url']);
        $this->assertSame(0, $this->research->notifications()->count());

        $formula->refresh()->load('document');
        $this->actingAs($this->regulatory)->post("/ticket-documents/{$formula->id}/reviews/pemakaian", ['decision' => 'perlu_revisi', 'note' => 'Komposisi salah.', 'token' => $formula->concurrencyToken()])
            ->assertSessionHasNoErrors();

        $returned = $this->research->notifications()->sole();
        $this->assertSame('Master formula perlu revisi', $returned->data['title']);
        $this->assertSame("{$this->ticket->code} · Komposisi salah.", $returned->data['body']);
        $this->assertSame("/tickets/{$this->ticket->id}", $returned->data['url']);
    }

    public function test_client_documents_go_to_the_client_marketing_or_the_manager_while_marketing_is_on_leave(): void
    {
        $powerOfAttorney = $this->item('Surat kuasa', 'client', DocumentScope::Client);
        $this->actingAs($this->regulatory);
        app(StaffNotifier::class)->documentReturned($powerOfAttorney, 'Tanda tangan kurang.');
        $this->assertSame(1, $this->marketing->notifications()->count());

        $this->marketing->forceFill(['unavailable_from' => '2026-11-01', 'unavailable_until' => '2026-11-30'])->save();
        app(StaffNotifier::class)->documentReturned($powerOfAttorney->refresh(), 'Tanda tangan kurang.');
        $this->assertSame(1, $this->marketing->notifications()->count());
        $this->assertSame(1, $this->manager->notifications()->count());
    }

    public function test_overdue_stages_escalate_to_the_manager_or_inform_marketing_when_waiting_for_the_client(): void
    {
        $sample = Stage::query()->where('code', 'pengembangan_sampel')->sole();
        $this->ticket->stageLogs()->create(['stage_id' => $sample->id, 'started_at' => now(), 'due_at' => now()->addHour()]);
        $clientTicket = Ticket::factory()->create(['client_id' => $this->ticket->client_id]);
        $documents = Stage::query()->where('code', 'dokumen_pra_registrasi')->sole();
        $clientTicket->stageLogs()->create(['stage_id' => $documents->id, 'started_at' => now(), 'due_at' => now()->addHour()]);
        $type = DocumentType::factory()->create(['pic_role' => 'client', 'phase' => StagePhase::PreRegistration]);
        $clientTicket->documents()->create(['document_type_id' => $type->id, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required, 'status' => DocumentStatus::Missing]);

        $this->travelTo('2026-11-05 12:00:00');
        $this->artisan('sla:check')->assertSuccessful();

        $this->assertSame('eskalasi_sla', $this->manager->notifications()->sole()->data['kind']);
        $this->assertSame('eskalasi_sla', $this->research->notifications()->sole()->data['kind']);
        $this->assertSame('menunggu_klien', $this->marketing->notifications()->sole()->data['kind']);
    }

    public function test_hourly_reminders_cover_stage_and_bpom_deadlines_once_each(): void
    {
        $sample = Stage::query()->where('code', 'pengembangan_sampel')->sole();
        $this->ticket->stageLogs()->create(['stage_id' => $sample->id, 'started_at' => now()->subDays(20), 'due_at' => '2026-11-06 09:00:00']);
        $later = Ticket::factory()->create();
        $later->stageLogs()->create(['stage_id' => $sample->id, 'started_at' => now(), 'due_at' => '2026-11-20 10:00:00']);

        $submission = $this->ticket->bpomSubmissions()->create(['phase' => StagePhase::PreRegistration, 'submitted_on' => '2026-10-01', 'pnbp' => 1, 'status' => SubmissionStatus::AwaitingResult, 'recorded_by' => $this->regulatory->id]);
        $submission->requests()->create(['ticket_id' => $this->ticket->id, 'sequence' => 3, 'note' => 'TD3', 'requested_on' => '2026-08-01', 'deadline' => '2026-11-10', 'recorded_by' => $this->regulatory->id]);
        $registrationTicket = Ticket::factory()->create();
        $registrationTicket->bpomSubmissions()->create(['phase' => StagePhase::PreRegistration, 'submitted_on' => '2026-09-01', 'pnbp' => 1, 'status' => SubmissionStatus::ResultIssued, 'result_on' => '2026-10-01', 'registration_deadline' => '2026-11-19', 'recorded_by' => $this->regulatory->id]);

        $this->artisan('notifications:deadlines')->expectsOutput('3 pengingat dikirim.')->assertSuccessful();
        $this->artisan('notifications:deadlines')->expectsOutput('0 pengingat dikirim.');

        $this->assertSame('Tenggat Pengembangan sampel besok', $this->research->notifications()->sole()->data['title']);
        $titles = $this->regulatory->notifications()->get()->pluck('data.title')->sort()->values()->all();
        $this->assertSame(['Pengajuan registrasi: 10 hari kerja lagi', 'Tambahan data TD3: 3 hari kerja lagi'], $titles);
        $this->assertSame(1, $this->manager->notifications()->count());
        $this->assertSame(3, BpomRequest::query()->value('reminded_days'));
    }

    public function test_the_notification_center_shows_only_own_notifications_and_marks_them_read(): void
    {
        $this->regulatory->notify(new WorkflowNotification('tenggat_bpom', 'Tenggat TD1', 'PRD-1 · tenggat 10/11/2026', "/tickets/{$this->ticket->id}", 'PRD-1'));
        $this->regulatory->notify(new WorkflowNotification('perlu_ditinjau', 'Perlu ditinjau', 'PRD-1', '//evil.test', 'PRD-1'));
        $this->marketing->notify(new WorkflowNotification('kontrak_ditandatangani', 'Kontrak', 'PRD-2', '/tickets/1'));

        $this->actingAs($this->regulatory)->withoutVite()->get('/notifications')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Backend/Notifications', false)->has('notifications.data', 2)->where('unread', 2)->where('workspace.notifications_unread', 2));

        $first = $this->regulatory->notifications()->where('data->title', 'Tenggat TD1')->sole();
        $this->get("/notifications/{$first->id}/open")->assertRedirect("/tickets/{$this->ticket->id}");
        $this->assertNotNull($first->refresh()->read_at);
        $unsafe = $this->regulatory->notifications()->where('data->title', 'Perlu ditinjau')->sole();
        $this->get("/notifications/{$unsafe->id}/open")->assertRedirect(route('notifications.index'));

        $foreign = $this->marketing->notifications()->sole();
        $this->get("/notifications/{$foreign->id}/open")->assertNotFound();

        $this->post('/notifications/read')->assertSessionHas('status', 'Semua notifikasi ditandai sudah dibaca.');
        $this->assertSame(0, $this->regulatory->unreadNotifications()->count());
        $this->assertSame(1, $this->marketing->unreadNotifications()->count());
    }

    private function item(string $name, string $pic, DocumentScope $scope = DocumentScope::Ticket): TicketDocument
    {
        $type = DocumentType::factory()->create(['name' => $name, 'pic_role' => $pic, 'verifier_role' => null, 'scope' => $scope, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required, 'requires_expiry_date' => false, 'max_age_months' => null]);

        return $this->ticket->documents()->create(['document_type_id' => $type->id, 'phase' => $type->phase, 'requirement' => $type->requirement, 'status' => DocumentStatus::Missing]);
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['email' => "{$role}@aisha.test"]);
        $user->assignRole($role);

        return $user;
    }
}
