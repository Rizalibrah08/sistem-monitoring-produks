<?php

namespace App\Http\Controllers;

use App\CriterionType;
use App\DocumentRequirement;
use App\DocumentScope;
use App\Models\Category;
use App\Models\Criterion;
use App\Models\DocumentType;
use App\Models\DosageForm;
use App\Models\Holiday;
use App\Models\Material;
use App\Models\Stage;
use App\StagePhase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
    /** @var list<string> */
    private const TABS = ['forms', 'ingredients', 'stages', 'documents', 'weights', 'holidays'];

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $tab = $request->string('tab')->toString();

        return Inertia::render('Backend/Master', [
            'tab' => in_array($tab, self::TABS, true) ? $tab : 'forms',
            'dosageForms' => DosageForm::query()->orderBy('name')->get()->map(fn (DosageForm $form): array => [
                'id' => $form->id,
                'name' => $form->name,
                'is_active' => $form->is_active,
                'update_url' => route('master.dosage-forms.update', $form, absolute: false),
            ]),
            'categories' => Category::query()->orderBy('name')->get()->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'is_active' => $category->is_active,
                'update_url' => route('master.categories.update', $category, absolute: false),
            ]),
            'materials' => Material::query()->orderBy('name')->get()->map(fn (Material $material): array => [
                'id' => $material->id,
                'name' => $material->name,
                'supplier' => $material->supplier,
                'is_active' => $material->is_active,
                'update_url' => route('master.materials.update', $material, absolute: false),
            ]),
            'stages' => Stage::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn (Stage $stage): array => [
                'id' => $stage->id,
                'code' => $stage->code,
                'name' => $stage->name,
                'phase' => $stage->phase->value,
                'phase_label' => $stage->phase->label(),
                'sort_order' => $stage->sort_order,
                'sla_days' => $stage->sla_days,
                'waits_for_external' => $stage->waits_for_external,
                'is_active' => $stage->is_active,
                'update_url' => route('master.stages.update', $stage, absolute: false),
            ]),
            'phases' => array_map(fn (StagePhase $phase): array => ['value' => $phase->value, 'label' => $phase->label()], StagePhase::cases()),
            'documentTypes' => DocumentType::query()->orderBy('name')->get()->map(fn (DocumentType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'phase' => $type->phase->value,
                'phase_label' => $type->phase->label(),
                'scope' => $type->scope->value,
                'scope_label' => $type->scope->label(),
                'requirement' => $type->requirement->value,
                'requirement_label' => $type->requirement->label(),
                'pic_role' => $type->pic_role,
                'pic_label' => DocumentType::roleLabel($type->pic_role),
                'verifier_role' => $type->verifier_role,
                'verifier_label' => DocumentType::roleLabel($type->verifier_role),
                'requires_expiry_date' => $type->requires_expiry_date,
                'max_age_months' => $type->max_age_months,
                'checks_product_name' => $type->checks_product_name,
                'is_active' => $type->is_active,
                'update_url' => route('master.document-types.update', $type, absolute: false),
            ]),
            'documentOptions' => [
                'phases' => array_map(fn (StagePhase $phase): array => ['value' => $phase->value, 'label' => $phase->label()], [StagePhase::PreRegistration, StagePhase::Registration, StagePhase::BothPhases]),
                'scopes' => array_map(fn (DocumentScope $scope): array => ['value' => $scope->value, 'label' => $scope->label()], DocumentScope::cases()),
                'requirements' => array_map(fn (DocumentRequirement $requirement): array => ['value' => $requirement->value, 'label' => $requirement->label()], DocumentRequirement::cases()),
                'pics' => array_map(fn (string $role): array => ['value' => $role, 'label' => DocumentType::roleLabel($role)], DocumentType::PIC_ROLES),
                'verifiers' => array_map(fn (string $role): array => ['value' => $role, 'label' => DocumentType::roleLabel($role)], DocumentType::VERIFIER_ROLES),
            ],
            'criteriaQueues' => $this->criteriaQueues(),
            'criterionTypes' => array_map(fn (CriterionType $type): array => ['value' => $type->value, 'label' => $type->label()], CriterionType::cases()),
            'holidays' => Holiday::query()->orderBy('date')->get()->map(fn (Holiday $holiday): array => [
                'id' => $holiday->id,
                'date' => $holiday->date,
                'name' => $holiday->name,
                'update_url' => route('master.holidays.update', $holiday, absolute: false),
                'delete_url' => route('master.holidays.destroy', $holiday, absolute: false),
            ]),
            'urls' => [
                'dosageForms' => route('master.dosage-forms.store', absolute: false),
                'categories' => route('master.categories.store', absolute: false),
                'materials' => route('master.materials.store', absolute: false),
                'stages' => route('master.stages.store', absolute: false),
                'documentTypes' => route('master.document-types.store', absolute: false),
                'holidays' => route('master.holidays.store', absolute: false),
            ],
        ]);
    }

    /** @return list<array{queue: string, label: string, update_url: string, criteria: list<array<string, mixed>>}> */
    private function criteriaQueues(): array
    {
        $criteria = Criterion::query()->orderBy('code')->get()->groupBy('queue');

        return array_map(fn (string $queue): array => [
            'queue' => $queue,
            'label' => Criterion::queueLabel($queue),
            'update_url' => route('master.criteria.update', $queue, absolute: false),
            'criteria' => $criteria->get($queue, collect())->map(fn (Criterion $criterion): array => [
                'code' => $criterion->code,
                'name' => $criterion->name,
                'weight' => $criterion->weight,
                'type' => $criterion->type->value,
            ])->values()->all(),
        ], Criterion::QUEUES);
    }
}
