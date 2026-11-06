<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentSource;
use App\DocumentStatus;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentRule;
use App\Models\DocumentType;
use App\Models\DosageForm;
use App\Models\Ticket;
use App\Models\User;
use App\StagePhase;
use App\TicketStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ?User $clientOwner = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 10:00:00');
    }

    public function test_only_marketing_creates_tickets_and_finance_cannot_see_them(): void
    {
        $research = $this->createStaff('rnd');
        $this->actingAs($research)->post('/tickets', $this->validTicket())->assertForbidden();

        $finance = $this->createStaff('finance', ['email' => 'finance@aisha.test']);
        $this->actingAs($finance)->get('/tickets')->assertForbidden();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_marketing_creates_a_ticket_that_starts_in_the_initial_phase_with_a_stage_log_and_audit(): void
    {
        $marketing = $this->createStaff('marketing');
        $data = $this->validTicket(['product_name' => 'Kapsul Jahe Merah']);

        $response = $this->actingAs($marketing)->post('/tickets', $data);

        $ticket = Ticket::query()->sole();
        $response->assertRedirect("/tickets/{$ticket->id}");
        $this->assertSame('PRD-2026-0001', $ticket->code);
        $this->assertSame(StagePhase::Initial, $ticket->phase);
        $this->assertSame(TicketStatus::Active, $ticket->status);
        $this->assertSame('pengembangan_sampel', $ticket->currentStage->code);
        $this->assertSame($marketing->id, $ticket->created_by);
        $log = $ticket->stageLogs()->sole();
        $this->assertSame('pengembangan_sampel', $log->stage->code);
        $this->assertSame('2026-11-05 10:00:00', $log->started_at->format('Y-m-d H:i:s'));
        $this->assertNull($log->finished_at);
        $this->assertDatabaseHas('activity_log', ['event' => 'ticket.created', 'subject_id' => $ticket->id, 'ticket_code' => 'PRD-2026-0001', 'causer_id' => $marketing->id]);
    }

    public function test_ticket_codes_continue_within_a_year_and_restart_in_a_new_year(): void
    {
        $marketing = $this->createStaff('marketing');
        Ticket::factory()->create(['code' => 'PRD-2026-0007']);

        $this->actingAs($marketing)->post('/tickets', $this->validTicket())->assertSessionHasNoErrors();
        $this->travelTo('2027-01-02 09:00:00');
        $this->withSession(['last_activity_at' => now()->timestamp])->post('/tickets', $this->validTicket())->assertSessionHasNoErrors();

        $this->assertSame(['PRD-2026-0007', 'PRD-2026-0008', 'PRD-2027-0001'], Ticket::query()->orderBy('id')->pluck('code')->all());
    }

    public function test_the_checklist_holds_required_documents_and_conditional_documents_whose_rules_apply(): void
    {
        $marketing = $this->createStaff('marketing');
        $syrup = DosageForm::factory()->create(['name' => 'Sirup']);
        $capsule = DosageForm::factory()->create(['name' => 'Kapsul']);
        $jamu = Category::factory()->create(['name' => 'Jamu']);
        $type = fn (string $name, array $attributes = []): DocumentType => DocumentType::factory()->create(['name' => $name, 'requirement' => DocumentRequirement::Required, 'scope' => DocumentScope::Ticket, 'phase' => StagePhase::PreRegistration, ...$attributes]);

        $formula = $type('Master formula');
        $powerOfAttorney = $type('Surat kuasa', ['scope' => DocumentScope::Client, 'phase' => StagePhase::BothPhases]);
        $halal = $type('Sertifikat halal', ['scope' => DocumentScope::Company, 'phase' => StagePhase::Registration]);
        $type('Asal peredaran bahan', ['scope' => DocumentScope::Material]);
        $type('Dokumen lama', ['is_active' => false]);
        $type('Foto produk', ['requirement' => DocumentRequirement::Optional]);
        $microbial = $type('Uji cemaran mikroba', ['requirement' => DocumentRequirement::Conditional]);
        $capsuleOnly = $type('Uji waktu hancur', ['requirement' => DocumentRequirement::Conditional]);
        $future = $type('Uji stabilitas', ['requirement' => DocumentRequirement::Conditional]);
        $syrupRule = DocumentRule::factory()->create(['document_type_id' => $microbial->id, 'dosage_form_id' => $syrup->id, 'category_id' => null, 'effective_from' => '2026-11-01']);
        DocumentRule::factory()->create(['document_type_id' => $capsuleOnly->id, 'dosage_form_id' => $capsule->id, 'category_id' => null, 'effective_from' => '2026-11-01']);
        DocumentRule::factory()->create(['document_type_id' => $future->id, 'dosage_form_id' => $syrup->id, 'category_id' => null, 'effective_from' => '2026-12-01']);

        $this->actingAs($marketing)->post('/tickets', $this->validTicket(['dosage_form_id' => $syrup->id, 'category_id' => $jamu->id]))->assertSessionHasNoErrors();

        $items = Ticket::query()->sole()->documents()->with('documentType')->get()->keyBy(fn ($item) => $item->documentType->name);
        $this->assertEqualsCanonicalizing(['Master formula', 'Surat kuasa', 'Sertifikat halal', 'Uji cemaran mikroba'], $items->keys()->all());
        $this->assertSame(DocumentStatus::Missing, $items['Master formula']->status);
        $this->assertSame(StagePhase::BothPhases, $items['Surat kuasa']->phase);
        $this->assertSame(DocumentRequirement::Required, $items['Sertifikat halal']->requirement);
        $this->assertSame(DocumentRequirement::Conditional, $items['Uji cemaran mikroba']->requirement);
        $this->assertSame($syrupRule->id, $items['Uji cemaran mikroba']->document_rule_id);
        $this->assertNull($items['Master formula']->document_rule_id);
        $this->assertSame(
            [$formula->id, $powerOfAttorney->id, $halal->id],
            [$items['Master formula']->document_type_id, $items['Surat kuasa']->document_type_id, $items['Sertifikat halal']->document_type_id],
        );
    }

    public function test_shared_client_and_company_documents_that_are_still_valid_are_linked_to_a_new_ticket(): void
    {
        $marketing = $this->createStaff('marketing');
        $client = Client::factory()->create(['marketing_user_id' => $marketing->id]);
        $type = fn (string $name, DocumentScope $scope, array $attributes = []): DocumentType => DocumentType::factory()->create(['name' => $name, 'requirement' => DocumentRequirement::Required, 'scope' => $scope, 'phase' => StagePhase::PreRegistration, 'max_age_months' => null, ...$attributes]);
        $sharedDocument = function (DocumentType $type, ?Client $owner, array $version): Document {
            $document = Document::factory()->create(['document_type_id' => $type->id, 'scope' => $type->scope, 'ticket_id' => null, 'client_id' => $owner?->id]);
            $current = $document->versions()->create(['version' => 1, 'path' => "documents/{$document->id}/v1.pdf", 'original_name' => 'v1.pdf', 'mime_type' => 'application/pdf', 'size' => 100, 'checksum' => str_repeat('a', 64), 'number' => 'DOC-1', 'source' => DocumentSource::Internal, 'validation_passed' => true, 'validation_errors' => [], ...$version]);
            $document->update(['current_version_id' => $current->id]);

            return $document;
        };

        $powerOfAttorney = $sharedDocument($type('Surat kuasa', DocumentScope::Client), $client, []);
        $sharedDocument($type('Surat pernyataan', DocumentScope::Client), Client::factory()->create(), []);
        $halal = $sharedDocument($type('Sertifikat halal', DocumentScope::Company, ['requires_expiry_date' => true]), null, ['expires_at' => '2027-06-30']);
        $sharedDocument($type('Sistem penomoran batch', DocumentScope::Company, ['requires_expiry_date' => true]), null, ['expires_at' => '2026-11-04']);
        $sharedDocument($type('Profil perusahaan', DocumentScope::Company, ['max_age_months' => 6]), null, ['issued_at' => '2026-04-01']);
        $sharedDocument($type('Denah produksi', DocumentScope::Company), null, ['validation_passed' => false, 'validation_errors' => ['Gagal.']]);

        $this->actingAs($marketing)->post('/tickets', $this->validTicket(['client_id' => $client->id]))->assertSessionHasNoErrors();

        $ticket = Ticket::query()->sole();
        $items = $ticket->documents()->with('documentType')->get()->keyBy(fn ($item) => $item->documentType->name);
        $this->assertSame($powerOfAttorney->id, $items['Surat kuasa']->document_id);
        $this->assertSame(DocumentStatus::UnderReview, $items['Surat kuasa']->status);
        $this->assertSame($halal->id, $items['Sertifikat halal']->document_id);
        foreach (['Surat pernyataan', 'Sistem penomoran batch', 'Profil perusahaan', 'Denah produksi'] as $name) {
            $this->assertNull($items[$name]->document_id, $name);
            $this->assertSame(DocumentStatus::Missing, $items[$name]->status, $name);
        }
        $entry = Activity::query()->where('event', 'ticket.created')->sole();
        $this->assertSame(2, $entry->attribute_changes['attributes']['linked_shared_documents']);
    }

    public function test_a_ticket_needs_an_existing_client_and_active_dosage_form_and_category(): void
    {
        $marketing = $this->createStaff('marketing');
        $retired = DosageForm::factory()->create(['is_active' => false]);

        $this->actingAs($marketing)->from('/tickets')->post('/tickets', [
            'client_id' => 999,
            'product_name' => '',
            'dosage_form_id' => $retired->id,
            'category_id' => null,
        ])->assertSessionHasErrors([
            'client_id' => 'Klien tidak ditemukan.',
            'product_name' => 'Nama produk wajib diisi.',
            'dosage_form_id' => 'Sediaan harus dipilih dari daftar sediaan aktif.',
            'category_id' => 'Kategori wajib dipilih.',
        ]);

        $colleague = $this->createStaff('marketing', ['email' => 'rekan@aisha.test']);
        $this->post('/tickets', $this->validTicket(['client_id' => Client::factory()->create(['marketing_user_id' => $colleague->id])->id]))
            ->assertSessionHasErrors(['client_id' => 'Pilih klien yang Anda tangani sebagai PIC Marketing.']);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_marketing_lists_only_tickets_of_their_own_clients_while_regulatory_affairs_sees_all(): void
    {
        $marketing = $this->createStaff('marketing');
        $colleague = $this->createStaff('marketing', ['email' => 'rekan@aisha.test']);
        Ticket::factory()->create(['code' => 'PRD-2026-0001', 'product_name' => 'Kapsul Jahe', 'client_id' => Client::factory()->create(['marketing_user_id' => $marketing->id])->id]);
        Ticket::factory()->create(['code' => 'PRD-2026-0002', 'product_name' => 'Serbuk Kunyit', 'client_id' => Client::factory()->create(['marketing_user_id' => $colleague->id])->id]);

        $this->withoutVite()->actingAs($marketing)->get('/tickets')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Tickets', false)
            ->has('tickets.data', 1)
            ->where('tickets.data.0.code', 'PRD-2026-0001')
            ->where('can.create', true)
            ->where('routes.tickets', '/tickets')
            ->where('navigation', fn ($items): bool => collect($items)->contains(fn (array $item): bool => $item['id'] === 'tickets' && $item['available'] === true)));

        $regulatory = $this->createStaff('ra', ['email' => 'ra@aisha.test']);
        $this->withoutVite()->actingAs($regulatory)->get('/tickets?search=kunyit')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.code', 'PRD-2026-0002')
            ->where('can.create', false));
    }

    public function test_the_ticket_detail_shows_the_checklist_per_phase_with_progress_and_its_history(): void
    {
        $marketing = $this->createStaff('marketing');
        DocumentType::factory()->create(['name' => 'Master formula', 'requirement' => DocumentRequirement::Required, 'phase' => StagePhase::PreRegistration]);
        DocumentType::factory()->create(['name' => 'Surat kuasa', 'requirement' => DocumentRequirement::Required, 'phase' => StagePhase::BothPhases]);
        DocumentType::factory()->create(['name' => 'Desain kemasan', 'requirement' => DocumentRequirement::Required, 'phase' => StagePhase::Registration]);
        $ownClient = Client::factory()->create(['marketing_user_id' => $marketing->id]);
        $this->actingAs($marketing)->post('/tickets', $this->validTicket(['product_name' => 'Kapsul Jahe Merah', 'client_id' => $ownClient->id]));
        $ticket = Ticket::query()->sole();
        $ticket->documents()->whereHas('documentType', fn ($query) => $query->where('name', 'Surat kuasa'))->update(['status' => DocumentStatus::Approved]);

        $this->withoutVite()->get("/tickets/{$ticket->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Backend/TicketShow', false)
            ->where('ticket.code', 'PRD-2026-0001')
            ->where('ticket.product_name', 'Kapsul Jahe Merah')
            ->where('ticket.phase_label', 'Awal')
            ->where('ticket.current_stage', 'Pengembangan sampel')
            ->has('checklist', 2)
            ->where('checklist.0.phase', 'pra_registrasi')
            ->where('checklist.0.progress', 50)
            ->where('checklist.0.documents', fn ($documents): bool => collect($documents)->pluck('name')->all() === ['Master formula', 'Surat kuasa'])
            ->where('checklist.1.phase', 'registrasi')
            ->where('checklist.1.progress', 50)
            ->has('stages', 1)
            ->where('stages.0.name', 'Pengembangan sampel')
            ->where('history.0.event', 'ticket.created'));

        $colleague = $this->createStaff('marketing', ['email' => 'rekan@aisha.test']);
        $this->actingAs($colleague)->get("/tickets/{$ticket->id}")->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validTicket(array $overrides = []): array
    {
        return [
            'client_id' => Client::factory()->create($this->clientOwner ? ['marketing_user_id' => $this->clientOwner->id] : [])->id,
            'product_name' => 'Serbuk Temulawak',
            'dosage_form_id' => DosageForm::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            ...$overrides,
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function createStaff(string $role, array $attributes = []): User
    {
        $this->seed();
        $user = User::factory()->create($attributes);
        $user->assignRole($role);
        $this->clientOwner ??= $role === 'marketing' ? $user : null;

        return $user;
    }
}
