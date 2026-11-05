<?php

namespace Tests\Feature;

use App\CriterionType;
use App\Models\Activity;
use App\Models\Criterion;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CriterionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeding_creates_the_consensus_ahp_weights_for_each_priority_queue(): void
    {
        $this->seed();

        $this->assertDatabaseCount('criteria', 16);

        foreach (['ra', 'marketing', 'rnd', 'qa'] as $queue) {
            $criteria = Criterion::query()->where('queue', $queue)->orderBy('code')->get();
            $this->assertSame(['C1' => 0.048, 'C2' => 0.74, 'C3' => 0.106, 'C4' => 0.106], $criteria->pluck('weight', 'code')->all());
            $this->assertEqualsWithDelta(1.0, $criteria->sum('weight'), 0.0001);
        }
    }

    public function test_document_completeness_is_a_benefit_only_in_the_regulatory_affairs_queue(): void
    {
        $this->seed();

        $this->assertSame(
            ['marketing' => 'cost', 'qa' => 'cost', 'ra' => 'benefit', 'rnd' => 'cost'],
            Criterion::query()->where('code', 'C3')->orderBy('queue')->get()->mapWithKeys(fn (Criterion $criterion): array => [$criterion->queue => $criterion->type->value])->all(),
        );
    }

    public function test_reseeding_keeps_weights_changed_by_admin(): void
    {
        $this->seed();
        Criterion::query()->where('queue', 'ra')->where('code', 'C1')->update(['weight' => 0.05]);

        $this->seed();

        $this->assertDatabaseCount('criteria', 16);
        $this->assertSame(0.05, Criterion::query()->where('queue', 'ra')->where('code', 'C1')->sole()->weight);
    }

    public function test_staff_without_master_access_cannot_change_criteria(): void
    {
        $regulatory = $this->createStaff('ra');

        $this->actingAs($regulatory)->put('/master/criteria/ra', $this->validCriteria())->assertForbidden();

        $this->assertSame(0.048, Criterion::query()->where('queue', 'ra')->where('code', 'C1')->sole()->weight);
    }

    public function test_admin_sees_the_criteria_of_every_queue_on_the_master_page(): void
    {
        $admin = $this->createStaff('admin');

        $this->withoutVite()->actingAs($admin)->get('/master?tab=weights')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'weights')
            ->has('criteriaQueues', 4)
            ->where('criteriaQueues.0.queue', 'ra')
            ->where('criteriaQueues.0.label', 'Regulatory Affairs')
            ->where('criteriaQueues.0.update_url', '/master/criteria/ra')
            ->has('criteriaQueues.0.criteria', 4)
            ->where('criteriaQueues.0.criteria.1.code', 'C2')
            ->where('criteriaQueues.0.criteria.1.name', 'Sisa hari kerja ke tenggat BPOM')
            ->where('criteriaQueues.0.criteria.1.weight', 0.74)
            ->where('criteriaQueues.0.criteria.2.type', 'benefit')
            ->where('criteriaQueues.1.criteria.2.type', 'cost'));
    }

    public function test_admin_updates_the_weights_of_one_queue_and_the_audit_keeps_both_values(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/master?tab=weights')->put('/master/criteria/ra', $this->validCriteria([
            'C1' => ['weight' => 0.05, 'type' => 'cost'],
            'C2' => ['weight' => 0.7, 'type' => 'cost'],
            'C3' => ['weight' => 0.125, 'type' => 'benefit'],
            'C4' => ['weight' => 0.125, 'type' => 'benefit'],
        ]))->assertRedirect('/master?tab=weights')->assertSessionHasNoErrors();

        $this->assertSame(0.7, Criterion::query()->where('queue', 'ra')->where('code', 'C2')->sole()->weight);
        $this->assertSame(0.74, Criterion::query()->where('queue', 'marketing')->where('code', 'C2')->sole()->weight);

        $entry = Activity::query()->where('event', 'master.criteria.updated')->sole();
        $this->assertSame('ra', $entry->properties['queue']);
        $this->assertSame(['weight' => 0.74, 'type' => 'cost'], $entry->attribute_changes['old']['C2']);
        $this->assertSame(['weight' => 0.7, 'type' => 'cost'], $entry->attribute_changes['attributes']['C2']);
    }

    public function test_weights_must_be_complete_valid_and_add_up_to_one(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/master?tab=weights')->put('/master/criteria/ra', $this->validCriteria([
            'C1' => ['weight' => 0.1, 'type' => 'cost'],
        ]))->assertSessionHasErrors(['criteria' => 'Total bobot harus 1,000 (saat ini 1,052).']);

        $this->put('/master/criteria/ra', ['criteria' => [
            'C1' => ['weight' => 0, 'type' => 'naik'],
            'C2' => ['weight' => 0.74, 'type' => 'cost'],
            'C3' => ['weight' => 0.1234, 'type' => 'benefit'],
        ]])->assertSessionHasErrors(['criteria.C1.weight', 'criteria.C1.type', 'criteria.C3.weight', 'criteria.C4']);

        $this->put('/master/criteria/finance', $this->validCriteria())->assertNotFound();

        $this->assertSame(0.048, Criterion::query()->where('queue', 'ra')->where('code', 'C1')->sole()->weight);
        $this->assertDatabaseMissing('activity_log', ['event' => 'master.criteria.updated']);
    }

    /**
     * @param  array<string, array{weight: float|int, type: string}>  $overrides
     * @return array{criteria: array<string, array{weight: float|int, type: string}>}
     */
    private function validCriteria(array $overrides = []): array
    {
        return ['criteria' => [
            'C1' => ['weight' => 0.048, 'type' => CriterionType::Cost->value],
            'C2' => ['weight' => 0.74, 'type' => CriterionType::Cost->value],
            'C3' => ['weight' => 0.106, 'type' => CriterionType::Benefit->value],
            'C4' => ['weight' => 0.106, 'type' => CriterionType::Benefit->value],
            ...$overrides,
        ]];
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
