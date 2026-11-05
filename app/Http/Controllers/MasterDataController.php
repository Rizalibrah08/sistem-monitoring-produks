<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\DosageForm;
use App\Models\Holiday;
use App\Models\Material;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterDataController extends Controller
{
    /** @var list<string> */
    private const TABS = ['forms', 'ingredients', 'holidays'];

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
                'holidays' => route('master.holidays.store', absolute: false),
            ],
        ]);
    }
}
