<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\Models\Activity;
use App\Models\Category;
use App\Models\DocumentRule;
use App\Models\DocumentType;
use App\Models\DosageForm;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentRuleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_and_staff_outside_admin_and_regulatory_affairs_are_forbidden(): void
    {
        $this->get('/document-rules')->assertRedirectToRoute('login');

        $marketing = $this->createStaff('marketing');
        $this->actingAs($marketing)->get('/document-rules')->assertForbidden();
        $this->post('/document-rules', $this->validRule())->assertForbidden();

        $this->assertDatabaseCount('document_rules', 0);
    }

    public function test_regulatory_affairs_adds_a_rule_from_the_menu_and_the_change_is_audited(): void
    {
        $regulatory = $this->createStaff('ra');

        $this->withoutVite()->actingAs($regulatory)->get('/document-rules')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Backend/DocumentRules', false)
            ->where('navigation', fn ($items): bool => collect($items)->contains(fn (array $item): bool => $item['id'] === 'document-rules' && $item['available'] === true)));

        $this->from('/document-rules')->post('/document-rules', $this->validRule())
            ->assertRedirect('/document-rules')->assertSessionHasNoErrors();

        $rule = DocumentRule::query()->sole();
        $this->assertSame('2026-12-01', $rule->effective_from);
        $this->assertTrue($rule->is_active);
        $this->assertDatabaseHas('activity_log', ['event' => 'master.document_rule.created', 'subject_id' => $rule->id, 'causer_id' => $regulatory->id]);
    }

    public function test_the_page_lists_rules_with_names_and_offers_only_active_conditional_document_types(): void
    {
        $admin = $this->createStaff('admin');
        $syrup = DosageForm::factory()->create(['name' => 'Sirup']);
        $conditional = DocumentType::factory()->create(['name' => 'Uji cemaran mikroba', 'requirement' => DocumentRequirement::Conditional]);
        DocumentType::factory()->create(['name' => 'CoA produk jadi', 'requirement' => DocumentRequirement::Required]);
        DocumentType::factory()->create(['name' => 'Dokumen lama', 'requirement' => DocumentRequirement::Conditional, 'is_active' => false]);
        DocumentRule::factory()->create(['document_type_id' => $conditional->id, 'dosage_form_id' => $syrup->id, 'category_id' => null, 'effective_from' => '2026-11-01']);

        $this->withoutVite()->actingAs($admin)->get('/document-rules')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('rules', 1)
            ->where('rules.0.document_type', 'Uji cemaran mikroba')
            ->where('rules.0.dosage_form', 'Sirup')
            ->where('rules.0.category', null)
            ->where('rules.0.effective_from', '2026-11-01')
            ->where('options.documentTypes', fn ($types): bool => collect($types)->pluck('label')->all() === ['Uji cemaran mikroba'])
            ->has('options.dosageForms', 1)
            ->where('urls.store', '/document-rules'));
    }

    public function test_a_rule_needs_a_conditional_document_a_dosage_form_or_category_and_a_valid_date(): void
    {
        $admin = $this->createStaff('admin');
        $required = DocumentType::factory()->create(['requirement' => DocumentRequirement::Required]);

        $this->actingAs($admin)->from('/document-rules')->post('/document-rules', [
            'document_type_id' => $required->id,
            'dosage_form_id' => null,
            'category_id' => null,
            'effective_from' => '01/12/2026',
        ])->assertSessionHasErrors([
            'document_type_id' => 'Aturan hanya untuk jenis dokumen kondisional yang aktif.',
            'dosage_form_id' => 'Pilih sediaan, kategori, atau keduanya.',
            'effective_from',
        ]);

        $this->assertDatabaseCount('document_rules', 0);
    }

    public function test_the_same_rule_cannot_be_registered_twice(): void
    {
        $admin = $this->createStaff('admin');
        $this->actingAs($admin)->from('/document-rules')->post('/document-rules', $this->validRule())->assertSessionHasNoErrors();
        $rule = DocumentRule::query()->sole();

        $this->post('/document-rules', [
            'document_type_id' => $rule->document_type_id,
            'dosage_form_id' => $rule->dosage_form_id,
            'category_id' => $rule->category_id,
            'effective_from' => $rule->effective_from,
        ])->assertSessionHasErrors(['document_type_id' => 'Aturan yang sama sudah terdaftar.']);

        $this->assertDatabaseCount('document_rules', 1);
    }

    public function test_admin_deactivates_a_rule_and_the_audit_keeps_both_values(): void
    {
        $admin = $this->createStaff('admin');
        $rule = DocumentRule::factory()->create(['effective_from' => '2026-11-01', 'is_active' => true]);

        $this->actingAs($admin)->from('/document-rules')->put("/document-rules/{$rule->id}", [
            'document_type_id' => $rule->document_type_id,
            'dosage_form_id' => $rule->dosage_form_id,
            'category_id' => $rule->category_id,
            'effective_from' => '2026-11-01',
            'notes' => 'Dihentikan sesuai arahan RA.',
            'is_active' => false,
        ])->assertRedirect('/document-rules')->assertSessionHasNoErrors();

        $this->assertFalse($rule->fresh()->is_active);
        $entry = Activity::query()->where('event', 'master.document_rule.updated')->sole();
        $this->assertTrue($entry->attribute_changes['old']['is_active']);
        $this->assertFalse($entry->attribute_changes['attributes']['is_active']);
    }

    public function test_only_active_rules_matching_the_product_and_already_effective_apply_to_a_new_ticket(): void
    {
        $syrup = DosageForm::factory()->create(['name' => 'Sirup']);
        $capsule = DosageForm::factory()->create(['name' => 'Kapsul']);
        $jamu = Category::factory()->create(['name' => 'Jamu']);
        $herbal = Category::factory()->create(['name' => 'Obat herbal terstandar']);
        $rule = fn (array $attributes): DocumentRule => DocumentRule::factory()->create(['effective_from' => '2026-11-01', 'is_active' => true, ...$attributes]);

        $forSyrup = $rule(['dosage_form_id' => $syrup->id, 'category_id' => null]);
        $forJamu = $rule(['dosage_form_id' => null, 'category_id' => $jamu->id]);
        $forSyrupJamu = $rule(['dosage_form_id' => $syrup->id, 'category_id' => $jamu->id]);
        $rule(['dosage_form_id' => $capsule->id, 'category_id' => null]);
        $rule(['dosage_form_id' => null, 'category_id' => $herbal->id]);
        $rule(['dosage_form_id' => $syrup->id, 'category_id' => null, 'effective_from' => '2026-11-06']);
        $rule(['dosage_form_id' => $syrup->id, 'category_id' => null, 'is_active' => false]);

        $applied = DocumentRule::query()->appliesTo($syrup, $jamu, Carbon::parse('2026-11-05 15:00', 'Asia/Jakarta'))->orderBy('id')->pluck('id')->all();

        $this->assertSame([$forSyrup->id, $forJamu->id, $forSyrupJamu->id], $applied);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validRule(array $overrides = []): array
    {
        return [
            'document_type_id' => DocumentType::factory()->create(['requirement' => DocumentRequirement::Conditional])->id,
            'dosage_form_id' => DosageForm::factory()->create()->id,
            'category_id' => null,
            'effective_from' => '2026-12-01',
            'notes' => 'Wajib untuk sediaan cair.',
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
