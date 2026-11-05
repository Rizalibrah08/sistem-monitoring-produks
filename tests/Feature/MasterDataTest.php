<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Category;
use App\Models\DosageForm;
use App\Models\Holiday;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/master')->assertRedirectToRoute('login');
    }

    public function test_staff_without_master_access_cannot_view_or_change_master_data(): void
    {
        $marketing = $this->createStaff('marketing');
        $form = DosageForm::factory()->create(['name' => 'Kapsul']);

        $this->actingAs($marketing)->get('/master')->assertForbidden();
        $this->post('/master/dosage-forms', ['name' => 'Serbuk'])->assertForbidden();
        $this->put("/master/dosage-forms/{$form->id}", ['name' => 'Tablet', 'is_active' => true])->assertForbidden();
        $this->post('/master/materials', ['name' => 'Jahe merah'])->assertForbidden();
        $this->post('/master/holidays', ['date' => '2026-12-25', 'name' => 'Hari Raya Natal'])->assertForbidden();

        $this->assertSame('Kapsul', $form->fresh()->name);
        $this->assertDatabaseCount('materials', 0);
        $this->assertDatabaseCount('holidays', 0);
        $this->assertDatabaseCount('activity_log', 0);
    }

    public function test_admin_sees_every_master_list_sorted_for_display(): void
    {
        $admin = $this->createStaff('admin');
        DosageForm::factory()->create(['name' => 'Serbuk']);
        DosageForm::factory()->create(['name' => 'Kapsul']);
        Category::factory()->create(['name' => 'Jamu']);
        Material::factory()->create(['name' => 'Jahe merah', 'supplier' => 'PT Rempah Nusantara']);
        Holiday::factory()->create(['date' => '2026-12-25', 'name' => 'Hari Raya Natal']);
        Holiday::factory()->create(['date' => '2026-08-17', 'name' => 'Hari Kemerdekaan']);

        $this->withoutVite()->actingAs($admin)->get('/master?tab=holidays')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Master', false)
            ->where('tab', 'holidays')
            ->where('dosageForms.0.name', 'Kapsul')
            ->where('dosageForms.1.name', 'Serbuk')
            ->where('categories.0.name', 'Jamu')
            ->where('materials.0.supplier', 'PT Rempah Nusantara')
            ->where('holidays.0.date', '2026-08-17')
            ->where('holidays.1.name', 'Hari Raya Natal')
            ->where('navigation', fn ($items): bool => collect($items)->contains(fn (array $item): bool => $item['id'] === 'master' && $item['available'] === true)));
    }

    public function test_an_unknown_tab_falls_back_to_dosage_forms_and_categories(): void
    {
        $admin = $this->createStaff('admin');

        $this->withoutVite()->actingAs($admin)->get('/master?tab=unknown')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tab', 'forms'));
    }

    public function test_admin_creates_a_dosage_form_that_is_active_and_audited(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/master?tab=forms')->post('/master/dosage-forms', ['name' => '  Kapsul  '])
            ->assertRedirect('/master?tab=forms')->assertSessionHasNoErrors();

        $form = DosageForm::query()->sole();
        $this->assertSame('Kapsul', $form->name);
        $this->assertTrue($form->is_active);
        $this->assertDatabaseHas('activity_log', ['event' => 'master.dosage_form.created', 'subject_id' => $form->id, 'causer_id' => $admin->id]);
    }

    public function test_names_are_required_and_unique_within_each_list(): void
    {
        $admin = $this->createStaff('admin');
        Category::factory()->create(['name' => 'Jamu']);

        $this->actingAs($admin)->from('/master')->post('/master/categories', ['name' => ''])
            ->assertSessionHasErrors(['name' => 'Nama wajib diisi.']);
        $this->post('/master/categories', ['name' => 'Jamu'])
            ->assertSessionHasErrors(['name' => 'Nama ini sudah ada di daftar.']);
        $this->post('/master/dosage-forms', ['name' => 'Jamu'])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('dosage_forms', 1);
    }

    public function test_admin_deactivates_a_category_and_the_audit_keeps_before_and_after_values(): void
    {
        $admin = $this->createStaff('admin');
        $category = Category::factory()->create(['name' => 'Jamu']);

        $this->actingAs($admin)->from('/master')->put("/master/categories/{$category->id}", ['name' => 'Jamu', 'is_active' => false])
            ->assertRedirect('/master')->assertSessionHasNoErrors();

        $this->assertFalse($category->fresh()->is_active);
        $entry = Activity::query()->where('event', 'master.category.updated')->sole();
        $this->assertSame(['name' => 'Jamu', 'is_active' => true], $entry->attribute_changes['old']);
        $this->assertSame(['name' => 'Jamu', 'is_active' => false], $entry->attribute_changes['attributes']);
    }

    public function test_research_staff_can_add_materials_but_only_admin_can_change_them(): void
    {
        $research = $this->createStaff('rnd');

        $this->actingAs($research)->from('/ingredients')->post('/master/materials', ['name' => 'Kayu manis', 'supplier' => 'CV Herba Lestari'])
            ->assertRedirect('/ingredients')->assertSessionHasNoErrors();

        $material = Material::query()->sole();
        $this->put("/master/materials/{$material->id}", ['name' => 'Kayu manis', 'supplier' => null, 'is_active' => false])->assertForbidden();
        $this->get('/master')->assertForbidden();
        $this->assertDatabaseHas('activity_log', ['event' => 'master.material.created', 'subject_id' => $material->id, 'causer_id' => $research->id]);

        $admin = $this->createStaff('admin', ['email' => 'admin@aisha.test']);
        $this->actingAs($admin)->from('/master?tab=ingredients')
            ->put("/master/materials/{$material->id}", ['name' => 'Kayu manis', 'supplier' => 'PT Rempah Nusantara', 'is_active' => true])
            ->assertRedirect('/master?tab=ingredients')->assertSessionHasNoErrors();

        $this->assertSame('PT Rempah Nusantara', $material->fresh()->supplier);
    }

    public function test_admin_manages_holidays_and_each_date_is_registered_once(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/master?tab=holidays')->post('/master/holidays', ['date' => '2026-12-25', 'name' => 'Hari Raya Natal'])
            ->assertRedirect('/master?tab=holidays')->assertSessionHasNoErrors();

        $holiday = Holiday::query()->sole();
        $this->assertSame('2026-12-25', $holiday->date);

        $this->post('/master/holidays', ['date' => '2026-12-25', 'name' => 'Cuti bersama'])
            ->assertSessionHasErrors(['date' => 'Tanggal ini sudah terdaftar sebagai hari libur.']);
        $this->post('/master/holidays', ['date' => '25/12/2026', 'name' => 'Format salah'])->assertSessionHasErrors('date');

        $this->put("/master/holidays/{$holiday->id}", ['date' => '2026-12-26', 'name' => 'Cuti bersama Natal'])->assertSessionHasNoErrors();
        $this->assertSame('2026-12-26', $holiday->fresh()->date);

        $this->delete("/master/holidays/{$holiday->id}")->assertRedirect('/master?tab=holidays');

        $this->assertDatabaseCount('holidays', 0);
        $this->assertSame(
            ['master.holiday.created', 'master.holiday.updated', 'master.holiday.deleted'],
            Activity::query()->orderBy('id')->pluck('event')->all(),
        );
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
