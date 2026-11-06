<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\DocumentScope;
use App\DocumentSource;
use App\DocumentStatus;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentRule;
use App\Models\DocumentType;
use App\Models\DosageForm;
use App\Models\Material;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\User;
use App\StagePhase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketMaterialTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-05 10:00:00');
    }

    public function test_research_chooses_materials_and_gets_a_checklist_item_per_material_or_a_linked_valid_document(): void
    {
        $research = $this->createStaff('rnd');
        $syrup = DosageForm::factory()->create();
        $ticket = Ticket::factory()->create(['dosage_form_id' => $syrup->id, 'category_id' => Category::factory()->create()->id]);
        $origin = $this->materialType('Asal peredaran bahan');
        $this->materialType('Dokumen bahan lama', ['is_active' => false]);
        $microbial = $this->materialType('Uji cemaran bahan', ['requirement' => DocumentRequirement::Conditional]);
        DocumentRule::factory()->create(['document_type_id' => $microbial->id, 'dosage_form_id' => $syrup->id, 'category_id' => null, 'effective_from' => '2026-11-01']);
        $ginger = Material::factory()->create(['name' => 'Jahe merah']);
        $turmeric = Material::factory()->create(['name' => 'Kunyit']);
        $gingerOrigin = $this->materialDocument($origin, $ginger, ['issued_at' => '2026-01-01']);

        $this->actingAs($research)->from("/tickets/{$ticket->id}")
            ->put("/tickets/{$ticket->id}/materials", ['material_ids' => [$ginger->id, $turmeric->id]])
            ->assertRedirect("/tickets/{$ticket->id}")->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Bahan baku diperbarui: 4 butir checklist ditambahkan, 1 langsung tertaut ke dokumen yang masih berlaku.');

        $this->assertEqualsCanonicalizing([$ginger->id, $turmeric->id], $ticket->materials()->pluck('materials.id')->all());
        $items = $ticket->documents()->with(['documentType', 'material'])->get()->keyBy(fn (TicketDocument $item): string => "{$item->documentType->name}|{$item->material->name}");
        $this->assertEqualsCanonicalizing(['Asal peredaran bahan|Jahe merah', 'Asal peredaran bahan|Kunyit', 'Uji cemaran bahan|Jahe merah', 'Uji cemaran bahan|Kunyit'], $items->keys()->all());
        $this->assertSame($gingerOrigin->id, $items['Asal peredaran bahan|Jahe merah']->document_id);
        $this->assertSame(DocumentStatus::UnderReview, $items['Asal peredaran bahan|Jahe merah']->status);
        $this->assertNull($items['Asal peredaran bahan|Kunyit']->document_id);
        $this->assertSame(DocumentStatus::Missing, $items['Asal peredaran bahan|Kunyit']->status);
        $this->assertSame(DocumentRequirement::Conditional, $items['Uji cemaran bahan|Kunyit']->requirement);

        $entry = Activity::query()->where('event', 'ticket.materials_updated')->sole();
        $this->assertSame($ticket->code, $entry->ticket_code);
        $this->assertSame([], $entry->attribute_changes['old']['materials']);
        $this->assertSame(['Jahe merah', 'Kunyit'], $entry->attribute_changes['attributes']['materials']);
    }

    public function test_material_documents_that_failed_validation_or_are_too_old_are_not_linked(): void
    {
        $research = $this->createStaff('rnd');
        $ticket = Ticket::factory()->create();
        $origin = $this->materialType('Asal peredaran bahan', ['max_age_months' => 6]);
        $old = Material::factory()->create();
        $failed = Material::factory()->create();
        $this->materialDocument($origin, $old, ['issued_at' => '2026-04-01']);
        $this->materialDocument($origin, $failed, ['issued_at' => '2026-10-01', 'validation_passed' => false, 'validation_errors' => ['Gagal.']]);

        $this->actingAs($research)->put("/tickets/{$ticket->id}/materials", ['material_ids' => [$old->id, $failed->id]])->assertSessionHasNoErrors();

        $this->assertSame(2, $ticket->documents()->whereNull('document_id')->where('status', DocumentStatus::Missing)->count());
    }

    public function test_removing_a_material_removes_only_its_checklist_items_and_keeps_its_documents(): void
    {
        $research = $this->createStaff('rnd');
        $ticket = Ticket::factory()->create();
        $origin = $this->materialType('Asal peredaran bahan');
        $ginger = Material::factory()->create();
        $turmeric = Material::factory()->create();
        $document = $this->materialDocument($origin, $ginger, []);

        $this->actingAs($research)->put("/tickets/{$ticket->id}/materials", ['material_ids' => [$ginger->id, $turmeric->id]])->assertSessionHasNoErrors();
        $this->put("/tickets/{$ticket->id}/materials", ['material_ids' => [$turmeric->id]])->assertSessionHasNoErrors();

        $this->assertSame([$turmeric->id], $ticket->materials()->pluck('materials.id')->all());
        $this->assertSame([$turmeric->id], $ticket->documents()->pluck('material_id')->all());
        $this->assertModelExists($document);

        $this->put("/tickets/{$ticket->id}/materials", ['material_ids' => [$turmeric->id]])->assertSessionHasNoErrors();
        $this->assertSame(2, Activity::query()->where('event', 'ticket.materials_updated')->count());
    }

    public function test_only_research_chooses_materials_and_new_materials_must_be_active(): void
    {
        $ticket = Ticket::factory()->create();
        $active = Material::factory()->create();
        $inactive = Material::factory()->create(['is_active' => false]);

        foreach (['marketing', 'qa', 'ra', 'manager'] as $role) {
            $this->actingAs($this->createStaff($role, ['email' => "{$role}@aisha.test"]))
                ->put("/tickets/{$ticket->id}/materials", ['material_ids' => [$active->id]])->assertForbidden();
        }

        $research = $this->createStaff('rnd', ['email' => 'rnd@aisha.test']);
        $this->actingAs($research)->from("/tickets/{$ticket->id}")
            ->put("/tickets/{$ticket->id}/materials", ['material_ids' => [$active->id, $inactive->id]])
            ->assertSessionHasErrors(['material_ids.1' => 'Bahan baku harus dipilih dari daftar bahan aktif.']);
        $this->assertSame(0, $ticket->materials()->count());

        $this->put("/tickets/{$ticket->id}/materials", ['material_ids' => [$active->id]])->assertSessionHasNoErrors();
        $active->update(['is_active' => false]);
        $this->put("/tickets/{$ticket->id}/materials", ['material_ids' => [$active->id]])->assertSessionHasNoErrors();
        $this->assertSame([$active->id], $ticket->materials()->pluck('materials.id')->all());
    }

    public function test_an_upload_for_a_material_item_is_owned_by_the_material_and_reused_by_the_next_ticket(): void
    {
        $research = $this->createStaff('rnd');
        $origin = $this->materialType('Asal peredaran bahan');
        $ginger = Material::factory()->create();
        $first = Ticket::factory()->create();
        $second = Ticket::factory()->create();

        $this->actingAs($research)->put("/tickets/{$first->id}/materials", ['material_ids' => [$ginger->id]])->assertSessionHasNoErrors();
        $item = $first->documents()->sole();
        $this->post("/tickets/{$first->id}/documents/{$item->id}/versions", [
            'file' => UploadedFile::fake()->create('asal-bahan.pdf', 100, 'application/pdf'),
            'number' => 'AP-1',
            'issued_at' => '2026-10-01',
        ])->assertSessionHasNoErrors();

        $document = $item->refresh()->document;
        $this->assertSame(DocumentScope::Material, $document->scope);
        $this->assertSame($ginger->id, $document->material_id);
        $this->assertNull($document->ticket_id);

        $this->put("/tickets/{$second->id}/materials", ['material_ids' => [$ginger->id]])->assertSessionHasNoErrors();
        $this->assertSame($document->id, $second->documents()->sole()->document_id);
        $this->assertSame($origin->id, $second->documents()->sole()->document_type_id);
    }

    public function test_the_ticket_page_shows_materials_and_lets_only_research_manage_them(): void
    {
        $research = $this->createStaff('rnd');
        $this->materialType('Asal peredaran bahan');
        $ticket = Ticket::factory()->create();
        $ginger = Material::factory()->create(['name' => 'Jahe merah', 'supplier' => 'CV Rempah']);
        Material::factory()->create(['name' => 'Bahan nonaktif', 'is_active' => false]);
        $this->actingAs($research)->put("/tickets/{$ticket->id}/materials", ['material_ids' => [$ginger->id]])->assertSessionHasNoErrors();

        $this->withoutVite()->get("/tickets/{$ticket->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('materials.0.name', 'Jahe merah')
                ->where('materialOptions', [['value' => $ginger->id, 'label' => 'Jahe merah (CV Rempah)']])
                ->where('can.manage_materials', true)
                ->where('urls.materials', "/tickets/{$ticket->id}/materials")
                ->where('checklist.0.documents.0.material', 'Jahe merah')
                ->where('history.0.label', 'Bahan baku diperbarui'));

        $quality = $this->createStaff('qa', ['email' => 'qa@aisha.test']);
        $this->actingAs($quality)->get("/tickets/{$ticket->id}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('materialOptions', null)->where('can.manage_materials', false));
    }

    /** @param array<string, mixed> $attributes */
    private function materialType(string $name, array $attributes = []): DocumentType
    {
        return DocumentType::factory()->create([
            'name' => $name,
            'requirement' => DocumentRequirement::Required,
            'scope' => DocumentScope::Material,
            'phase' => StagePhase::PreRegistration,
            'pic_role' => 'rnd',
            'requires_expiry_date' => false,
            'max_age_months' => null,
            ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $version */
    private function materialDocument(DocumentType $type, Material $material, array $version): Document
    {
        $document = Document::factory()->create(['document_type_id' => $type->id, 'scope' => DocumentScope::Material, 'ticket_id' => null, 'material_id' => $material->id]);
        $current = $document->versions()->create([
            'version' => 1, 'path' => "documents/{$document->id}/v1.pdf", 'original_name' => 'v1.pdf', 'mime_type' => 'application/pdf', 'size' => 100,
            'checksum' => str_repeat('a', 64), 'number' => 'AP-1', 'source' => DocumentSource::Internal, 'validation_passed' => true, 'validation_errors' => [], ...$version,
        ]);
        $document->update(['current_version_id' => $current->id]);

        return $document;
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
