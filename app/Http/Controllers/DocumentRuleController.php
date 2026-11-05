<?php

namespace App\Http\Controllers;

use App\Actions\SaveMasterRecord;
use App\DocumentRequirement;
use App\Http\Requests\SaveDocumentRuleRequest;
use App\Models\Category;
use App\Models\DocumentRule;
use App\Models\DocumentType;
use App\Models\DosageForm;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DocumentRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Backend/DocumentRules', [
            'rules' => DocumentRule::query()->with(['documentType', 'dosageForm', 'category'])
                ->orderByDesc('effective_from')->orderByDesc('id')->get()
                ->map(fn (DocumentRule $rule): array => [
                    'id' => $rule->id,
                    'document_type_id' => $rule->document_type_id,
                    'document_type' => $rule->documentType->name,
                    'dosage_form_id' => $rule->dosage_form_id,
                    'dosage_form' => $rule->dosageForm?->name,
                    'category_id' => $rule->category_id,
                    'category' => $rule->category?->name,
                    'effective_from' => $rule->effective_from,
                    'notes' => $rule->notes,
                    'is_active' => $rule->is_active,
                    'update_url' => route('document-rules.update', $rule, absolute: false),
                ]),
            'options' => [
                'documentTypes' => DocumentType::query()->where('requirement', DocumentRequirement::Conditional)->where('is_active', true)
                    ->orderBy('name')->get()->map(fn (DocumentType $type): array => ['value' => $type->id, 'label' => $type->name]),
                'dosageForms' => DosageForm::query()->where('is_active', true)->orderBy('name')->get()
                    ->map(fn (DosageForm $form): array => ['value' => $form->id, 'label' => $form->name]),
                'categories' => Category::query()->where('is_active', true)->orderBy('name')->get()
                    ->map(fn (Category $category): array => ['value' => $category->id, 'label' => $category->name]),
            ],
            'urls' => ['store' => route('document-rules.store', absolute: false)],
        ]);
    }

    public function store(SaveDocumentRuleRequest $request, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle(new DocumentRule, 'document_rule', $request->validated(), $request->user());

        return back()->with('status', 'Aturan dokumen berhasil ditambahkan.');
    }

    public function update(SaveDocumentRuleRequest $request, DocumentRule $documentRule, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle($documentRule, 'document_rule', $request->validated(), $request->user());

        return back()->with('status', 'Aturan dokumen berhasil diperbarui.');
    }
}
