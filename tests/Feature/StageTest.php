<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Stage;
use App\Models\User;
use App\StagePhase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeding_creates_the_confirmed_stages_with_their_working_day_sla(): void
    {
        $this->seed();

        $this->assertSame([
            'pengembangan_sampel' => 14,
            'kontrak' => 1,
            'dokumen_pra_registrasi' => 1,
            'verifikasi_pra_registrasi' => 1,
            'uji_laboratorium' => 14,
            'menunggu_bpom_pra_registrasi' => null,
            'dokumen_registrasi' => 30,
            'verifikasi_pengajuan_registrasi' => 1,
            'menunggu_bpom_registrasi' => null,
            'unggah_desain_kemasan' => null,
            'input_nie' => 1,
            'perbaikan_dokumen' => 5,
        ], Stage::query()->orderBy('sort_order')->pluck('sla_days', 'code')->all());
        $this->assertSame(
            ['menunggu_bpom_pra_registrasi', 'menunggu_bpom_registrasi', 'unggah_desain_kemasan'],
            Stage::query()->where('waits_for_external', true)->orderBy('sort_order')->pluck('code')->all(),
        );
    }

    public function test_reseeding_keeps_the_changes_made_by_admin(): void
    {
        $this->seed();
        Stage::query()->where('code', 'pengembangan_sampel')->update(['name' => 'Sampel', 'sla_days' => 10]);

        $this->seed();

        $this->assertDatabaseCount('stages', 12);
        $this->assertDatabaseHas('stages', ['code' => 'pengembangan_sampel', 'name' => 'Sampel', 'sla_days' => 10]);
    }

    public function test_staff_without_master_access_cannot_change_stages(): void
    {
        $regulatory = $this->createStaff('ra');
        $stage = Stage::query()->where('code', 'input_nie')->sole();

        $this->actingAs($regulatory)->post('/master/stages', $this->validStage())->assertForbidden();
        $this->put("/master/stages/{$stage->id}", $this->validStage(['sla_days' => 9]))->assertForbidden();

        $this->assertDatabaseCount('stages', 12);
        $this->assertSame(1, $stage->fresh()->sla_days);
    }

    public function test_admin_sees_stages_in_process_order_on_the_master_page(): void
    {
        $admin = $this->createStaff('admin');

        $this->withoutVite()->actingAs($admin)->get('/master?tab=stages')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Master', false)
            ->where('tab', 'stages')
            ->has('stages', 12)
            ->where('stages.0.name', 'Pengembangan sampel')
            ->where('stages.0.phase', 'awal')
            ->where('stages.0.phase_label', 'Awal')
            ->where('stages.0.sla_days', 14)
            ->where('stages.5.code', 'menunggu_bpom_pra_registrasi')
            ->where('stages.5.waits_for_external', true)
            ->where('stages.5.sla_days', null)
            ->has('phases', 4)
            ->where('urls.stages', '/master/stages'));
    }

    public function test_admin_adds_a_stage_with_a_stable_code_and_an_audit_entry(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/master?tab=stages')
            ->post('/master/stages', $this->validStage(['name' => 'Tanggapan tambahan data']))
            ->assertRedirect('/master?tab=stages')->assertSessionHasNoErrors();

        $stage = Stage::query()->where('name', 'Tanggapan tambahan data')->sole();
        $this->assertSame('tanggapan_tambahan_data', $stage->code);
        $this->assertSame(StagePhase::Registration, $stage->phase);
        $this->assertTrue($stage->is_active);
        $this->assertDatabaseHas('activity_log', ['event' => 'master.stage.created', 'subject_id' => $stage->id, 'causer_id' => $admin->id]);
    }

    public function test_admin_adds_a_waiting_stage_without_sla(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/master?tab=stages')
            ->post('/master/stages', $this->validStage(['name' => 'Menunggu laboratorium mitra', 'waits_for_external' => true, 'sla_days' => null]))
            ->assertSessionHasNoErrors();

        $stage = Stage::query()->where('code', 'menunggu_laboratorium_mitra')->sole();
        $this->assertTrue($stage->waits_for_external);
        $this->assertNull($stage->sla_days);
    }

    public function test_renaming_a_stage_keeps_its_code_and_audits_the_sla_change(): void
    {
        $admin = $this->createStaff('admin');
        $stage = Stage::query()->where('code', 'perbaikan_dokumen')->sole();

        $this->actingAs($admin)->from('/master?tab=stages')->put("/master/stages/{$stage->id}", $this->validStage([
            'name' => 'Revisi dokumen',
            'phase' => 'kedua_fase',
            'sort_order' => 12,
            'sla_days' => 3,
            'code' => 'kode_baru',
        ]))->assertRedirect('/master?tab=stages')->assertSessionHasNoErrors();

        $stage->refresh();
        $this->assertSame('perbaikan_dokumen', $stage->code);
        $this->assertSame('Revisi dokumen', $stage->name);
        $this->assertSame(3, $stage->sla_days);
        $entry = Activity::query()->where('event', 'master.stage.updated')->sole();
        $this->assertSame(5, $entry->attribute_changes['old']['sla_days']);
        $this->assertSame(3, $entry->attribute_changes['attributes']['sla_days']);
    }

    public function test_internal_stages_need_an_sla_and_waiting_stages_cannot_have_one(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/master?tab=stages')
            ->post('/master/stages', $this->validStage(['sla_days' => null]))
            ->assertSessionHasErrors(['sla_days' => 'SLA wajib diisi untuk tahap internal.']);
        $this->post('/master/stages', $this->validStage(['waits_for_external' => true, 'sla_days' => 7]))
            ->assertSessionHasErrors(['sla_days' => 'Tahap yang menunggu pihak luar tidak memakai SLA.']);
        $this->post('/master/stages', $this->validStage(['phase' => 'final', 'sla_days' => 0, 'sort_order' => 'awal']))
            ->assertSessionHasErrors(['phase', 'sla_days', 'sort_order']);
        $this->post('/master/stages', $this->validStage(['name' => 'Input NIE']))
            ->assertSessionHasErrors(['name' => 'Tahap dengan nama ini sudah ada.']);

        $this->assertDatabaseCount('stages', 12);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validStage(array $overrides = []): array
    {
        return [
            'name' => 'Tahap pengujian',
            'phase' => 'registrasi',
            'sort_order' => 13,
            'sla_days' => 2,
            'waits_for_external' => false,
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
