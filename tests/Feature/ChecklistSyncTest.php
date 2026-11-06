<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentStatus;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentRule;
use App\Models\DocumentType;
use App\Models\DosageForm;
use App\Models\Material;
use App\Models\Ticket;
use App\Models\User;
use App\StagePhase;
use App\SubmissionStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChecklistSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $regulatory;

    private Ticket $ticket;

    private DosageForm $syrup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 10:00:00');
        $this->seed();
        $this->regulatory = User::factory()->create(['email' => 'ra@aisha.test']);
        $this->regulatory->assignRole('ra');
        $this->syrup = DosageForm::factory()->create();
        $this->ticket = Ticket::factory()->create(['dosage_form_id' => $this->syrup->id, 'category_id' => Category::factory()->create()->id, 'phase' => StagePhase::PreRegistration, 'created_at' => '2026-10-01 10:00:00']);
    }

    public function test_new_rules_and_required_types_are_added_and_no_longer_required_empty_items_are_removed(): void
    {
        $formula = $this->type('Master formula');
        $this->item($formula);
        $retired = $this->type('Formulir lama', ['is_active' => false]);
        $this->item($retired);
        $filled = $this->type('Dokumen berisi', ['is_active' => false]);
        $this->item($filled, DocumentStatus::UnderReview, withDocument: true);
        $newType = $this->type('Uji stabilitas');
        $conditional = $this->type('Uji cemaran mikroba', ['requirement' => DocumentRequirement::Conditional]);
        DocumentRule::factory()->create(['document_type_id' => $conditional->id, 'dosage_form_id' => $this->syrup->id, 'category_id' => null, 'effective_from' => '2026-11-01']);
        $origin = $this->type('Asal peredaran bahan', ['scope' => DocumentScope::Material]);
        $this->ticket->materials()->attach(Material::factory()->create(['name' => 'Jahe merah'])->id);

        $this->actingAs($this->regulatory)->withoutVite()->get('/sync-checklist')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Backend/ChecklistSync', false)->has('tickets', 1)
                ->where('tickets.0.add', ['Uji stabilitas', 'Uji cemaran mikroba', 'Asal peredaran bahan · Jahe merah'])
                ->where('tickets.0.remove', ['Formulir lama']));
        $this->get("/tickets/{$this->ticket->id}")->assertInertia(fn (Assert $page) => $page->where('checklistSync.remove', ['Formulir lama']));

        $this->post("/tickets/{$this->ticket->id}/checklist-sync")->assertSessionHas('status', 'Checklist disinkronkan: 3 butir ditambahkan, 1 butir dilepas.');

        $types = $this->ticket->documents()->pluck('document_type_id')->all();
        $this->assertEqualsCanonicalizing([$formula->id, $filled->id, $newType->id, $conditional->id, $origin->id], $types);
        $this->assertSame(DocumentStatus::Missing, $this->ticket->documents()->where('document_type_id', $newType->id)->sole()->status);
        $this->assertSame(['Formulir lama'], Activity::query()->where('event', 'checklist.synced')->sole()->attribute_changes['attributes']['removed']);
        $this->get('/sync-checklist')->assertInertia(fn (Assert $page) => $page->has('tickets', 0));
    }

    public function test_phases_already_submitted_to_bpom_are_left_unchanged(): void
    {
        $this->type('Uji stabilitas');
        $this->type('Desain kemasan', ['phase' => StagePhase::Registration]);
        $this->ticket->bpomSubmissions()->create(['phase' => StagePhase::PreRegistration, 'submitted_on' => '2026-10-20', 'pnbp' => 1, 'status' => SubmissionStatus::AwaitingResult, 'recorded_by' => $this->regulatory->id]);

        $this->actingAs($this->regulatory)->post("/tickets/{$this->ticket->id}/checklist-sync")->assertSessionHas('status', 'Checklist disinkronkan: 1 butir ditambahkan, 0 butir dilepas.');
        $this->assertSame(['Desain kemasan'], $this->ticket->documents()->with('documentType')->get()->pluck('documentType.name')->all());
    }

    public function test_only_staff_who_manage_document_rules_sync_checklists(): void
    {
        $marketing = User::factory()->create(['email' => 'marketing@aisha.test']);
        $marketing->assignRole('marketing');

        $this->actingAs($marketing)->get('/sync-checklist')->assertForbidden();
        $this->post("/tickets/{$this->ticket->id}/checklist-sync")->assertForbidden();
    }

    /** @param array<string, mixed> $attributes */
    private function type(string $name, array $attributes = []): DocumentType
    {
        return DocumentType::factory()->create(['name' => $name, 'phase' => StagePhase::PreRegistration, 'scope' => DocumentScope::Ticket, 'requirement' => DocumentRequirement::Required, 'is_active' => true, ...$attributes]);
    }

    private function item(DocumentType $type, DocumentStatus $status = DocumentStatus::Missing, bool $withDocument = false): void
    {
        $document = $withDocument ? Document::factory()->create(['document_type_id' => $type->id, 'ticket_id' => $this->ticket->id]) : null;
        $this->ticket->documents()->create(['document_type_id' => $type->id, 'phase' => $type->phase, 'requirement' => $type->requirement, 'document_id' => $document?->id, 'status' => $status]);
    }
}
