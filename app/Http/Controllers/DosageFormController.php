<?php

namespace App\Http\Controllers;

use App\Actions\SaveMasterRecord;
use App\Http\Requests\SaveDosageFormRequest;
use App\Models\DosageForm;
use Illuminate\Http\RedirectResponse;

class DosageFormController extends Controller
{
    public function store(SaveDosageFormRequest $request, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle(new DosageForm, 'dosage_form', $request->validated(), $request->user());

        return back()->with('status', 'Sediaan berhasil ditambahkan.');
    }

    public function update(SaveDosageFormRequest $request, DosageForm $dosageForm, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle($dosageForm, 'dosage_form', $request->validated(), $request->user());

        return back()->with('status', 'Sediaan berhasil diperbarui.');
    }
}
