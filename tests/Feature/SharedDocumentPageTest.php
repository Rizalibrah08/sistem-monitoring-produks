<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentStatus;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Material;
use App\Models\Ticket;
use App\Models\TicketReview;
use App\Models\User;
use App\StagePhase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedDocumentPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-05 10:00:00');
        $this->seed();
    }

    public function test_shared_documents_are_listed_with_owner_versions_expiry_and_active_tickets(): void
    {
        $client = Client::factory()->create(['name' => 'Nusantara Herbal']);
        $attorney = $this->document($this->type('Surat kuasa', DocumentScope::Client), ['client_id' => $client->id]);
        $this->document($this->type('Asal peredaran bahan', DocumentScope::Material), ['material_id' => Material::factory()->create(['name' => 'Jahe merah'])->id]);
        $halal = $this->document($this->type('Sertifikat halal', DocumentScope::Company), [], '2026-11-20');
        $this->document($this->type('Master formula', DocumentScope::Ticket), ['ticket_id' => Ticket::factory()->create()->id]);
        $ticket = Ticket::factory()->create(['client_id' => $client->id]);
        $ticket->documents()->create(['document_type_id' => $attorney->document_type_id, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required, 'document_id' => $attorney->id, 'status' => DocumentStatus::UnderReview]);

        $this->actingAs($this->staff('qa'))->withoutVite()->get('/shared-documents')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Backend/SharedDocuments', false)
                ->has('documents', 3)
                ->where('documents.0.name', 'Asal peredaran bahan')->where('documents.0.owner', 'Jahe merah')
                ->where('documents.1.name', 'Sertifikat halal')->where('documents.1.expiring', true)
                ->where('documents.2.owner', 'Nusantara Herbal')->where('documents.2.active_tickets', 1)
                ->where('companyTypes.0.label', 'Sertifikat halal'));

        $this->get('/shared-documents?scope=perusahaan')->assertInertia(fn (Assert $page) => $page->has('documents', 1)->where('documents.0.id', $halal->id));
        $this->get("/document-versions/{$halal->current_version_id}/download")->assertOk();
    }

    public function test_company_documents_are_uploaded_as_validated_versions_that_flag_tickets_using_them(): void
    {
        $halalType = $this->type('Sertifikat halal', DocumentScope::Company, ['requires_expiry_date' => true]);
        $regulatory = $this->staff('ra');

        $this->actingAs($regulatory)->from('/shared-documents')->post('/shared-documents', $this->upload($halalType, ['expires_at' => '']))
            ->assertSessionHasErrors(['expires_at' => 'Tanggal kedaluwarsa wajib diisi untuk dokumen dengan masa berlaku.']);
        $this->post('/shared-documents', $this->upload($halalType))->assertSessionHas('status', 'Versi 1 tersimpan dan lolos validasi otomatis.');

        $document = Document::query()->where('document_type_id', $halalType->id)->sole();
        $this->assertSame(DocumentScope::Company, $document->scope);
        $ticket = Ticket::factory()->create();
        $ticket->documents()->create(['document_type_id' => $halalType->id, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required, 'document_id' => $document->id, 'status' => DocumentStatus::Approved]);

        $this->post('/shared-documents', $this->upload($halalType, ['expires_at' => '2026-10-01']))
            ->assertSessionHas('status', 'Versi 2 tersimpan, tetapi perlu revisi: Dokumen sudah kedaluwarsa pada 01/10/2026.');
        $this->assertSame(2, $document->refresh()->versions()->count());
        $this->assertSame($ticket->id, TicketReview::query()->sole()->ticket_id);

        $clientType = $this->type('Surat kuasa', DocumentScope::Client);
        $this->post('/shared-documents', $this->upload($clientType))->assertSessionHasErrors(['document_type_id' => 'Pilih jenis dokumen bersama perusahaan yang aktif.']);
    }

    public function test_the_ingredients_page_lists_ticket_materials_with_their_documents_for_research(): void
    {
        $origin = $this->type('Asal peredaran bahan', DocumentScope::Material);
        $ginger = Material::factory()->create(['name' => 'Jahe merah', 'supplier' => 'CV Rempah']);
        $ticket = Ticket::factory()->create();
        $ticket->materials()->attach($ginger->id);
        $ticket->documents()->create(['document_type_id' => $origin->id, 'material_id' => $ginger->id, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required, 'status' => DocumentStatus::Missing]);
        Ticket::factory()->create();

        $this->actingAs($this->staff('rnd'))->withoutVite()->get('/ingredients')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Backend/Ingredients', false)->has('rows', 1)
                ->where('rows.0.material', 'Jahe merah')->where('rows.0.documents.0.status_label', 'Belum ada'));
    }

    public function test_other_roles_cannot_open_or_change_shared_documents(): void
    {
        $halal = $this->document($this->type('Sertifikat halal', DocumentScope::Company), []);

        $this->actingAs($this->staff('marketing'))->get('/shared-documents')->assertForbidden();
        $this->post('/shared-documents', [])->assertForbidden();
        $this->get("/document-versions/{$halal->current_version_id}/download")->assertForbidden();
        $this->get('/ingredients')->assertForbidden();
    }

    /** @param array<string, mixed> $attributes */
    private function type(string $name, DocumentScope $scope, array $attributes = []): DocumentType
    {
        return DocumentType::factory()->create(['name' => $name, 'scope' => $scope, 'phase' => StagePhase::PreRegistration, 'requirement' => DocumentRequirement::Required, 'is_active' => true, 'requires_expiry_date' => false, 'max_age_months' => null, ...$attributes]);
    }

    /** @param array<string, mixed> $owner */
    private function document(DocumentType $type, array $owner, ?string $expiresAt = null): Document
    {
        $document = Document::factory()->create(['document_type_id' => $type->id, 'scope' => $type->scope, 'ticket_id' => null, ...$owner]);
        Storage::disk('local')->put("documents/{$document->id}/v1.pdf", '%PDF');
        $version = $document->versions()->create([
            'version' => 1, 'path' => "documents/{$document->id}/v1.pdf", 'original_name' => 'v1.pdf', 'mime_type' => 'application/pdf', 'size' => 4,
            'checksum' => str_repeat('a', 64), 'number' => 'DOC-1', 'expires_at' => $expiresAt, 'source' => 'internal', 'validation_passed' => true, 'validation_errors' => [],
        ]);
        $document->update(['current_version_id' => $version->id]);

        return $document->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function upload(DocumentType $type, array $overrides = []): array
    {
        return ['document_type_id' => $type->id, 'file' => UploadedFile::fake()->create('halal.pdf', 100, 'application/pdf'), 'number' => 'HL-1', 'issued_at' => '2026-10-01', 'expires_at' => '2027-10-01', ...$overrides];
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['email' => "{$role}@aisha.test"]);
        $user->assignRole($role);

        return $user;
    }
}
