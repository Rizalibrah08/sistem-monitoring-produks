<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\DocumentScope;
use App\Models\Activity;
use App\Models\DocumentType;
use App\Models\User;
use App\StagePhase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentTypeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_staff_without_master_access_cannot_change_document_types(): void
    {
        $quality = $this->createStaff('qa');
        $type = DocumentType::factory()->create(['name' => 'CoA produk jadi']);

        $this->actingAs($quality)->post('/master/document-types', $this->validDocumentType())->assertForbidden();
        $this->put("/master/document-types/{$type->id}", $this->validDocumentType(['name' => 'CoA']))->assertForbidden();

        $this->assertDatabaseCount('document_types', 1);
        $this->assertSame('CoA produk jadi', $type->fresh()->name);
    }

    public function test_admin_sees_document_types_with_labels_and_the_allowed_options(): void
    {
        $admin = $this->createStaff('admin');
        DocumentType::factory()->create([
            'name' => 'CoA produk jadi',
            'phase' => StagePhase::BothPhases,
            'scope' => DocumentScope::Ticket,
            'requirement' => DocumentRequirement::Required,
            'pic_role' => 'qa',
            'verifier_role' => 'qa',
            'requires_expiry_date' => false,
            'max_age_months' => 6,
        ]);

        $this->withoutVite()->actingAs($admin)->get('/master?tab=documents')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'documents')
            ->has('documentTypes', 1)
            ->where('documentTypes.0.name', 'CoA produk jadi')
            ->where('documentTypes.0.phase_label', 'Kedua fase')
            ->where('documentTypes.0.scope_label', 'Tiket')
            ->where('documentTypes.0.requirement_label', 'Wajib')
            ->where('documentTypes.0.pic_label', 'QA/QC')
            ->where('documentTypes.0.verifier_label', 'QA/QC')
            ->where('documentTypes.0.max_age_months', 6)
            ->where('documentOptions.phases', fn ($phases): bool => collect($phases)->pluck('value')->all() === ['pra_registrasi', 'registrasi', 'kedua_fase'])
            ->has('documentOptions.scopes', 4)
            ->has('documentOptions.requirements', 3)
            ->where('documentOptions.pics', fn ($pics): bool => collect($pics)->pluck('value')->all() === ['marketing', 'rnd', 'qa', 'ra', 'client'])
            ->where('documentOptions.verifiers', fn ($verifiers): bool => collect($verifiers)->pluck('value')->all() === ['qa', 'ra'])
            ->where('urls.documentTypes', '/master/document-types'));
    }

    public function test_admin_adds_a_document_type_with_an_age_limit_and_an_audit_entry(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/master?tab=documents')->post('/master/document-types', $this->validDocumentType([
            'name' => 'CoA bahan baku',
            'phase' => 'kedua_fase',
            'scope' => 'tiket',
            'pic_role' => 'qa',
            'verifier_role' => 'qa',
            'requires_expiry_date' => false,
            'max_age_months' => 6,
        ]))->assertRedirect('/master?tab=documents')->assertSessionHasNoErrors();

        $type = DocumentType::query()->sole();
        $this->assertSame(StagePhase::BothPhases, $type->phase);
        $this->assertSame(DocumentScope::Ticket, $type->scope);
        $this->assertSame(DocumentRequirement::Required, $type->requirement);
        $this->assertSame(6, $type->max_age_months);
        $this->assertTrue($type->is_active);
        $this->assertDatabaseHas('activity_log', ['event' => 'master.document_type.created', 'subject_id' => $type->id, 'causer_id' => $admin->id]);
    }

    public function test_a_client_can_be_the_person_in_charge_and_verification_can_be_left_to_regulatory_affairs(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/master?tab=documents')->post('/master/document-types', $this->validDocumentType([
            'name' => 'Surat kuasa',
            'scope' => 'klien',
            'pic_role' => 'client',
            'verifier_role' => null,
            'requires_expiry_date' => false,
        ]))->assertSessionHasNoErrors();

        $type = DocumentType::query()->sole();
        $this->assertSame('client', $type->pic_role);
        $this->assertNull($type->verifier_role);
    }

    public function test_document_types_reject_values_outside_the_two_registration_phases_and_known_roles(): void
    {
        $admin = $this->createStaff('admin');
        DocumentType::factory()->create(['name' => 'Sertifikat halal']);

        $this->actingAs($admin)->from('/master?tab=documents')->post('/master/document-types', $this->validDocumentType([
            'name' => 'Sertifikat halal',
            'phase' => 'awal',
            'scope' => 'gudang',
            'requirement' => 'mungkin',
            'pic_role' => 'finance',
            'verifier_role' => 'marketing',
            'max_age_months' => 0,
        ]))->assertSessionHasErrors([
            'name' => 'Jenis dokumen dengan nama ini sudah ada.',
            'phase' => 'Fase dokumen harus pra-registrasi, registrasi, atau kedua fase.',
            'scope', 'requirement', 'pic_role', 'verifier_role', 'max_age_months',
        ]);

        $this->assertDatabaseCount('document_types', 1);
    }

    public function test_admin_changes_the_validity_rules_and_the_audit_keeps_both_values(): void
    {
        $admin = $this->createStaff('admin');
        $type = DocumentType::factory()->create(['name' => 'Sertifikat halal', 'requires_expiry_date' => false, 'max_age_months' => 12]);

        $this->actingAs($admin)->from('/master?tab=documents')->put("/master/document-types/{$type->id}", $this->validDocumentType([
            'name' => 'Sertifikat halal',
            'requires_expiry_date' => true,
            'max_age_months' => null,
        ]))->assertSessionHasNoErrors();

        $type->refresh();
        $this->assertTrue($type->requires_expiry_date);
        $this->assertNull($type->max_age_months);
        $entry = Activity::query()->where('event', 'master.document_type.updated')->sole();
        $this->assertSame([false, 12], [$entry->attribute_changes['old']['requires_expiry_date'], $entry->attribute_changes['old']['max_age_months']]);
        $this->assertSame([true, null], [$entry->attribute_changes['attributes']['requires_expiry_date'], $entry->attribute_changes['attributes']['max_age_months']]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validDocumentType(array $overrides = []): array
    {
        return [
            'name' => 'Sistem penomoran batch',
            'phase' => 'registrasi',
            'scope' => 'perusahaan',
            'requirement' => 'wajib',
            'pic_role' => 'qa',
            'verifier_role' => 'qa',
            'requires_expiry_date' => false,
            'max_age_months' => null,
            'is_active' => true,
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
