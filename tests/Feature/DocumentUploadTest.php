<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentSource;
use App\DocumentStatus;
use App\Models\Client;
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

class DocumentUploadTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-05 10:00:00');
    }

    public function test_the_person_in_charge_uploads_a_new_version_that_passes_automatic_validation(): void
    {
        $research = $this->createStaff('rnd');
        $item = $this->checklistItem(['name' => 'Master formula', 'pic_role' => 'rnd']);

        $this->actingAs($research)->from("/tickets/{$item->ticket_id}")
            ->post($this->uploadUrl($item), $this->upload(['number' => 'MF/2026/01', 'issued_at' => '2026-10-01']))
            ->assertRedirect("/tickets/{$item->ticket_id}")->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame(DocumentStatus::UnderReview, $item->status);
        $version = $item->document->versions()->sole();
        $this->assertSame(1, $version->version);
        $this->assertSame('MF/2026/01', $version->number);
        $this->assertSame('2026-10-01', $version->issued_at->format('Y-m-d'));
        $this->assertSame(DocumentSource::Internal, $version->source);
        $this->assertSame($research->id, $version->uploaded_by);
        $this->assertTrue($version->validation_passed);
        $this->assertSame([], $version->validation_errors);
        $this->assertSame($version->id, $item->document->current_version_id);
        Storage::disk('local')->assertExists($version->path);
        $this->assertDatabaseHas('activity_log', ['event' => 'document.uploaded', 'ticket_code' => $item->ticket->code, 'causer_id' => $research->id]);
    }

    public function test_every_upload_becomes_the_next_version_and_older_versions_are_kept(): void
    {
        $research = $this->createStaff('rnd');
        $item = $this->checklistItem(['pic_role' => 'rnd']);

        $this->actingAs($research)->post($this->uploadUrl($item), $this->upload(['number' => 'A-1']))->assertSessionHasNoErrors();
        $this->post($this->uploadUrl($item), $this->upload(['number' => 'A-2']))->assertSessionHasNoErrors();

        $document = $item->refresh()->document;
        $this->assertSame([1, 2], $document->versions()->orderBy('version')->pluck('version')->all());
        $this->assertSame('A-2', $document->currentVersion->number);
        $this->assertCount(2, Storage::disk('local')->allFiles("documents/{$document->id}"));
    }

    public function test_only_the_allowed_formats_up_to_twenty_megabytes_are_accepted(): void
    {
        $research = $this->createStaff('rnd');
        $item = $this->checklistItem(['pic_role' => 'rnd']);

        $this->actingAs($research)->from('/tickets')
            ->post($this->uploadUrl($item), $this->upload(['file' => UploadedFile::fake()->create('formula.exe', 10)]))
            ->assertSessionHasErrors(['file' => 'Format berkas harus PDF, JPG, PNG, DOCX, atau XLSX.']);
        $this->post($this->uploadUrl($item), $this->upload(['file' => UploadedFile::fake()->create('formula.pdf', 20 * 1024 + 1, 'application/pdf')]))
            ->assertSessionHasErrors(['file' => 'Ukuran berkas maksimal 20 MB.']);
        $this->post($this->uploadUrl($item), $this->upload(['file' => UploadedFile::fake()->create('formula.xlsx', 20 * 1024)]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $item->refresh()->document->versions()->count());
    }

    public function test_the_issue_and_expiry_dates_are_required_when_the_document_type_needs_them(): void
    {
        $quality = $this->createStaff('qa');
        $item = $this->checklistItem(['name' => 'CoA produk jadi', 'pic_role' => 'qa', 'max_age_months' => 6, 'requires_expiry_date' => true]);

        $this->actingAs($quality)->from('/tickets')->post($this->uploadUrl($item), [
            'file' => UploadedFile::fake()->create('coa.pdf', 100, 'application/pdf'),
            'number' => '',
            'issued_at' => '',
            'expires_at' => '',
        ])->assertSessionHasErrors([
            'number' => 'Nomor dokumen wajib diisi.',
            'issued_at' => 'Tanggal terbit wajib diisi untuk dokumen dengan umur maksimal.',
            'expires_at' => 'Tanggal kedaluwarsa wajib diisi untuk dokumen dengan masa berlaku.',
        ]);

        $this->assertNull($item->refresh()->document_id);
    }

    public function test_documents_older_than_their_age_limit_or_already_expired_are_stored_but_returned_for_revision(): void
    {
        $quality = $this->createStaff('qa');
        $coa = $this->checklistItem(['name' => 'CoA produk jadi', 'pic_role' => 'qa', 'max_age_months' => 6]);
        $halal = $this->checklistItem(['name' => 'Sertifikat halal', 'pic_role' => 'qa', 'requires_expiry_date' => true, 'scope' => DocumentScope::Company]);

        $this->actingAs($quality)->post($this->uploadUrl($coa), $this->upload(['issued_at' => '2026-03-01']))->assertSessionHasNoErrors();
        $this->post($this->uploadUrl($halal), $this->upload(['issued_at' => '2024-10-01', 'expires_at' => '2026-10-01']))->assertSessionHasNoErrors();

        $coa->refresh();
        $this->assertSame(DocumentStatus::NeedsRevision, $coa->status);
        $this->assertFalse($coa->document->currentVersion->validation_passed);
        $this->assertSame(['Umur dokumen melebihi 6 bulan sejak terbit (01/03/2026).'], $coa->document->currentVersion->validation_errors);

        $halal->refresh();
        $this->assertSame(DocumentStatus::NeedsRevision, $halal->status);
        $this->assertSame(['Dokumen sudah kedaluwarsa pada 01/10/2026.'], $halal->document->currentVersion->validation_errors);
    }

    public function test_shared_documents_are_owned_by_the_client_or_the_company_instead_of_the_ticket(): void
    {
        $marketing = $this->createStaff('marketing');
        $clientItem = $this->checklistItem(['name' => 'Surat kuasa', 'pic_role' => 'client', 'scope' => DocumentScope::Client], $marketing);
        $companyItem = $this->checklistItem(['name' => 'Sistem penomoran batch', 'pic_role' => 'marketing', 'scope' => DocumentScope::Company], $marketing);

        $this->actingAs($marketing)->post($this->uploadUrl($clientItem), $this->upload())->assertSessionHasNoErrors();
        $this->post($this->uploadUrl($companyItem), $this->upload())->assertSessionHasNoErrors();

        $clientDocument = $clientItem->refresh()->document;
        $this->assertSame($clientItem->ticket->client_id, $clientDocument->client_id);
        $this->assertNull($clientDocument->ticket_id);
        $companyDocument = $companyItem->refresh()->document;
        $this->assertNull($companyDocument->client_id);
        $this->assertNull($companyDocument->ticket_id);
    }

    public function test_staff_who_are_not_the_person_in_charge_cannot_upload(): void
    {
        $quality = $this->createStaff('qa');
        $item = $this->checklistItem(['pic_role' => 'rnd']);

        $this->actingAs($quality)->post($this->uploadUrl($item), $this->upload())->assertForbidden();

        $regulatory = $this->createStaff('ra', ['email' => 'ra@aisha.test']);
        $this->actingAs($regulatory)->post($this->uploadUrl($item), $this->upload())->assertSessionHasNoErrors();

        $this->assertSame(1, $item->refresh()->document->versions()->count());
    }

    public function test_versions_are_downloaded_only_by_staff_who_can_see_the_ticket(): void
    {
        $marketing = $this->createStaff('marketing');
        $item = $this->checklistItem(['pic_role' => 'marketing'], $marketing);
        $this->actingAs($marketing)->post($this->uploadUrl($item), $this->upload(['file' => UploadedFile::fake()->create('formula.pdf', 50, 'application/pdf')]));
        $version = $item->refresh()->document->currentVersion;

        $this->get("/document-versions/{$version->id}/download")->assertOk()->assertDownload('formula.pdf');

        $colleague = $this->createStaff('marketing', ['email' => 'rekan@aisha.test']);
        $this->actingAs($colleague)->get("/document-versions/{$version->id}/download")->assertForbidden();
        $finance = $this->createStaff('finance', ['email' => 'finance@aisha.test']);
        $this->actingAs($finance)->get("/document-versions/{$version->id}/download")->assertForbidden();
    }

    public function test_the_ticket_page_shows_the_current_version_its_revision_reason_and_who_may_upload(): void
    {
        $quality = $this->createStaff('qa');
        $item = $this->checklistItem(['name' => 'CoA produk jadi', 'pic_role' => 'qa', 'max_age_months' => 6]);
        $this->actingAs($quality)->post($this->uploadUrl($item), $this->upload(['number' => 'COA-9', 'issued_at' => '2026-03-01']));
        $version = $item->refresh()->document->currentVersion;

        $this->withoutVite()->get("/tickets/{$item->ticket_id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('checklist.0.documents.0.status', 'perlu_revisi')
            ->where('checklist.0.documents.0.revision_reason', 'Umur dokumen melebihi 6 bulan sejak terbit (01/03/2026).')
            ->where('checklist.0.documents.0.requires_issued_at', true)
            ->where('checklist.0.documents.0.can_upload', true)
            ->where('checklist.0.documents.0.upload_url', $this->uploadUrl($item))
            ->where('checklist.0.documents.0.current_version.number', 'COA-9')
            ->where('checklist.0.documents.0.current_version.download_url', "/document-versions/{$version->id}/download"));

        $research = $this->createStaff('rnd', ['email' => 'rnd@aisha.test']);
        $this->withoutVite()->actingAs($research)->get("/tickets/{$item->ticket_id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('checklist.0.documents.0.can_upload', false));
    }

    /** @param array<string, mixed> $typeAttributes */
    private function checklistItem(array $typeAttributes = [], ?User $marketing = null): TicketDocument
    {
        $type = DocumentType::factory()->create([
            'requirement' => DocumentRequirement::Required,
            'phase' => StagePhase::PreRegistration,
            'scope' => DocumentScope::Ticket,
            'requires_expiry_date' => false,
            'max_age_months' => null,
            ...$typeAttributes,
        ]);
        $client = Client::factory()->create($marketing ? ['marketing_user_id' => $marketing->id] : []);
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);

        return $ticket->documents()->create([
            'document_type_id' => $type->id,
            'phase' => $type->phase,
            'requirement' => $type->requirement,
            'status' => DocumentStatus::Missing,
        ]);
    }

    private function uploadUrl(TicketDocument $item): string
    {
        return "/tickets/{$item->ticket_id}/documents/{$item->id}/versions";
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function upload(array $overrides = []): array
    {
        return [
            'file' => UploadedFile::fake()->create('dokumen.pdf', 200, 'application/pdf'),
            'number' => 'DOC/2026/001',
            'issued_at' => '2026-10-01',
            'expires_at' => null,
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
