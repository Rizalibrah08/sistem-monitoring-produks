<?php

namespace App\Http\Controllers;

use App\Actions\StoreTemplateVersion;
use App\Http\Requests\UploadTemplateRequest;
use App\Models\DocumentTemplate;
use App\Models\TemplateVersion;
use App\TemplateKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Backend/Templates', [
            'templates' => DocumentTemplate::query()
                ->with(['versions' => fn ($query) => $query->with('uploader')->orderByDesc('version')])
                ->orderBy('name')->get()
                ->map(fn (DocumentTemplate $template): array => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'kind' => $template->kind->value,
                    'kind_label' => $template->kind->label(),
                    'is_active' => $template->is_active,
                    'current_version' => $template->versions->first()?->version,
                    'upload_url' => route('templates.versions.store', $template, absolute: false),
                    'versions' => $template->versions->map(fn (TemplateVersion $version): array => [
                        'id' => $version->id,
                        'version' => $version->version,
                        'original_name' => $version->original_name,
                        'size' => $version->size,
                        'placeholders' => $version->placeholders,
                        'notes' => $version->notes,
                        'uploaded_by' => $version->uploader->name,
                        'uploaded_at' => $version->created_at?->format('d/m/Y H:i'),
                        'download_url' => route('templates.versions.download', $version, absolute: false),
                    ])->values(),
                ]),
            'kinds' => array_map(fn (TemplateKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], TemplateKind::cases()),
            'variables' => collect(config('document_templates.variables'))
                ->map(fn (string $description, string $name): array => ['name' => $name, 'description' => $description])->values(),
            'urls' => ['store' => route('templates.store', absolute: false)],
        ]);
    }

    public function store(UploadTemplateRequest $request, StoreTemplateVersion $store): RedirectResponse
    {
        $template = new DocumentTemplate($request->safe()->only(['name', 'kind']));
        $store->handle($template, $request->file('file'), $request->placeholders(), $request->user(), $request->validated('notes'));

        return back()->with('status', 'Templat berhasil diunggah sebagai versi 1.');
    }

    public function storeVersion(UploadTemplateRequest $request, DocumentTemplate $documentTemplate, StoreTemplateVersion $store): RedirectResponse
    {
        $version = $store->handle($documentTemplate, $request->file('file'), $request->placeholders(), $request->user(), $request->validated('notes'));

        return back()->with('status', "Templat berhasil diunggah sebagai versi {$version->version}.");
    }

    public function download(TemplateVersion $templateVersion): StreamedResponse
    {
        return Storage::disk('local')->download($templateVersion->path, "{$templateVersion->template->name} v{$templateVersion->version}.docx");
    }
}
