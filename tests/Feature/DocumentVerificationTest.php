<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\User;
use App\StagePhase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $quality;

    private User $regulatory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-05 10:00:00');
        $this->quality = $this->createStaff('qa');
        $this->regulatory = $this->createStaff('ra');
    }

    public function test_quality_documents_go_through_qa_content_verification_before_regulatory_usage_approval(): void
    {
        $coa = $this->item('CoA produk jadi', 'qa');
        $this->item('CoA gagal validasi', 'qa', validationPassed: false);
        $this->item('Belum diunggah', 'qa', uploaded: false);
        $formula = $this->item('Master formula', null);

        $this->actingAs($this->quality)->withoutVite()->get('/qa-review')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Backend/Verification', false)->where('level', 'isi')->has('items', 1)->where('items.0.id', $coa->id));
        $this->actingAs($this->regulatory)->get('/verification')
            ->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.id', $formula->id));

        $this->actingAs($this->regulatory)->from('/verification')->post($this->reviewUrl($coa, 'pemakaian'), $this->decision($coa, 'disetujui'))
            ->assertSessionHasErrors(['token' => 'Isi dokumen mutu harus diverifikasi QA/QC terlebih dahulu.']);

        $this->actingAs($this->quality)->from('/qa-review')->post($this->reviewUrl($coa, 'isi'), $this->decision($coa, 'disetujui'))
            ->assertRedirect('/qa-review')->assertSessionHas('status', 'CoA produk jadi disetujui.');
        $this->assertSame(DocumentStatus::UnderReview, $coa->refresh()->status);

        $this->actingAs($this->regulatory)->get('/verification')
            ->assertInertia(fn (Assert $page) => $page->has('items', 2)->where('items', fn ($items): bool => collect($items)->firstWhere('id', $coa->id)['content_verified'] === true));
        $this->post($this->reviewUrl($coa, 'pemakaian'), $this->decision($coa->refresh(), 'disetujui'))->assertSessionHasNoErrors();

        $this->assertSame(DocumentStatus::Approved, $coa->refresh()->status);
        $this->assertSame(['isi', 'pemakaian'], $coa->reviews()->orderBy('id')->pluck('level')->map->value->all());
        $this->assertDatabaseHas('activity_log', ['event' => 'document.reviewed', 'ticket_code' => $coa->ticket->code, 'causer_id' => $this->regulatory->id]);
    }

    public function test_returning_a_document_needs_a_reason_and_opens_the_repair_stage_until_a_new_version_arrives(): void
    {
        $formula = $this->item('Master formula', null, picRole: 'rnd');

        $this->actingAs($this->regulatory)->from('/verification')->post($this->reviewUrl($formula, 'pemakaian'), $this->decision($formula, 'perlu_revisi'))
            ->assertSessionHasErrors(['note' => 'Alasan revisi wajib diisi.']);
        $this->post($this->reviewUrl($formula, 'pemakaian'), $this->decision($formula, 'perlu_revisi', 'Komposisi tidak sesuai sampel.'))->assertSessionHasNoErrors();

        $formula->refresh();
        $this->assertSame(DocumentStatus::NeedsRevision, $formula->status);
        $this->assertSame('Komposisi tidak sesuai sampel.', $formula->revision_reason);
        $repair = $formula->ticket->stageLogs()->whereHas('stage', fn ($query) => $query->where('code', 'perbaikan_dokumen'))->sole();
        $this->assertNull($repair->finished_at);

        $this->actingAs($this->createStaff('rnd', ['email' => 'rnd@aisha.test']))->post("/tickets/{$formula->ticket_id}/documents/{$formula->id}/versions", [
            'file' => UploadedFile::fake()->create('formula.pdf', 100, 'application/pdf'), 'number' => 'MF-2', 'issued_at' => '2026-11-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame(DocumentStatus::UnderReview, $formula->refresh()->status);
        $this->assertNotNull($repair->refresh()->finished_at);
    }

    public function test_a_content_approval_of_a_shared_version_is_reused_by_other_tickets(): void
    {
        $first = $this->item('Sertifikat halal', 'qa', scope: DocumentScope::Company);
        $second = Ticket::factory()->create()->documents()->create([
            'document_type_id' => $first->document_type_id, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required,
            'document_id' => $first->document_id, 'status' => DocumentStatus::UnderReview,
        ]);

        $this->actingAs($this->quality)->post($this->reviewUrl($first, 'isi'), $this->decision($first, 'disetujui'))->assertSessionHasNoErrors();

        $this->get('/qa-review')->assertInertia(fn (Assert $page) => $page->has('items', 0));
        $this->actingAs($this->regulatory)->get('/verification')->assertInertia(fn (Assert $page) => $page->has('items', 2));
        $this->post($this->reviewUrl($second, 'pemakaian'), $this->decision($second, 'disetujui'))->assertSessionHasNoErrors();
        $this->assertSame(DocumentStatus::Approved, $second->refresh()->status);
        $this->assertSame(DocumentStatus::UnderReview, $first->refresh()->status);
    }

    public function test_a_decision_on_data_changed_by_someone_else_is_rejected(): void
    {
        $formula = $this->item('Master formula', null);
        $staleToken = $formula->concurrencyToken();
        $formula->update(['status' => DocumentStatus::NeedsRevision, 'revision_reason' => 'Dikembalikan RA lain.']);

        $this->actingAs($this->regulatory)->from('/verification')->post($this->reviewUrl($formula, 'pemakaian'), ['decision' => 'disetujui', 'token' => $staleToken])
            ->assertSessionHasErrors(['token' => 'Dokumen ini sudah diubah pengguna lain. Muat ulang halaman lalu periksa kembali.']);
        $this->assertSame(0, $formula->reviews()->count());
    }

    public function test_each_role_only_makes_its_own_level_of_decision(): void
    {
        $coa = $this->item('CoA produk jadi', 'qa');

        $this->actingAs($this->quality)->post($this->reviewUrl($coa, 'pemakaian'), $this->decision($coa, 'disetujui'))->assertForbidden();
        $this->actingAs($this->regulatory)->post($this->reviewUrl($coa, 'isi'), $this->decision($coa, 'disetujui'))->assertForbidden();
        $this->actingAs($this->createStaff('marketing', ['email' => 'marketing@aisha.test']))->get('/verification')->assertForbidden();
        $this->get('/qa-review')->assertForbidden();
        $this->assertSame(0, $coa->reviews()->count());
    }

    private function item(string $name, ?string $verifier, bool $validationPassed = true, bool $uploaded = true, DocumentScope $scope = DocumentScope::Ticket, string $picRole = 'qa'): TicketDocument
    {
        $type = DocumentType::factory()->create([
            'name' => $name, 'phase' => StagePhase::PreRegistration, 'scope' => $scope, 'requirement' => DocumentRequirement::Required,
            'pic_role' => $picRole, 'verifier_role' => $verifier, 'requires_expiry_date' => false, 'max_age_months' => null,
        ]);
        $ticket = Ticket::factory()->create();
        $document = null;

        if ($uploaded) {
            $document = Document::factory()->create(['document_type_id' => $type->id, 'scope' => $scope, 'ticket_id' => $scope === DocumentScope::Ticket ? $ticket->id : null]);
            $version = $document->versions()->create([
                'version' => 1, 'path' => "documents/{$document->id}/v1.pdf", 'original_name' => 'v1.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
                'checksum' => str_repeat('a', 64), 'number' => 'DOC-1', 'source' => 'internal', 'validation_passed' => $validationPassed, 'validation_errors' => $validationPassed ? [] : ['Kedaluwarsa.'],
            ]);
            $document->update(['current_version_id' => $version->id]);
        }

        return $ticket->documents()->create([
            'document_type_id' => $type->id, 'phase' => $type->phase, 'requirement' => $type->requirement, 'document_id' => $document?->id,
            'status' => ! $uploaded ? DocumentStatus::Missing : ($validationPassed ? DocumentStatus::UnderReview : DocumentStatus::NeedsRevision),
        ]);
    }

    private function reviewUrl(TicketDocument $item, string $level): string
    {
        return "/ticket-documents/{$item->id}/reviews/{$level}";
    }

    /** @return array<string, string> */
    private function decision(TicketDocument $item, string $decision, string $note = ''): array
    {
        return ['decision' => $decision, 'note' => $note, 'token' => $item->load('document')->concurrencyToken()];
    }

    /** @param array<string, mixed> $attributes */
    private function createStaff(string $role, array $attributes = []): User
    {
        $this->seed();
        $user = User::factory()->create(['email' => "{$role}@aisha.test", ...$attributes]);
        $user->assignRole($role);

        return $user;
    }
}
