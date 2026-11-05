<?php

namespace App\Http\Controllers;

use App\Actions\SaveMasterRecord;
use App\Http\Requests\SaveDocumentTypeRequest;
use App\Models\DocumentType;
use Illuminate\Http\RedirectResponse;

class DocumentTypeController extends Controller
{
    public function store(SaveDocumentTypeRequest $request, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle(new DocumentType, 'document_type', $request->validated(), $request->user());

        return back()->with('status', 'Jenis dokumen berhasil ditambahkan.');
    }

    public function update(SaveDocumentTypeRequest $request, DocumentType $documentType, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle($documentType, 'document_type', $request->validated(), $request->user());

        return back()->with('status', 'Jenis dokumen berhasil diperbarui.');
    }
}
