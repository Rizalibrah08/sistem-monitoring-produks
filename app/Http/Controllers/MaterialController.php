<?php

namespace App\Http\Controllers;

use App\Actions\SaveMasterRecord;
use App\Http\Requests\SaveMaterialRequest;
use App\Models\Material;
use Illuminate\Http\RedirectResponse;

class MaterialController extends Controller
{
    public function store(SaveMaterialRequest $request, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle(new Material, 'material', $request->validated(), $request->user());

        return back()->with('status', 'Bahan baku berhasil ditambahkan.');
    }

    public function update(SaveMaterialRequest $request, Material $material, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle($material, 'material', $request->validated(), $request->user());

        return back()->with('status', 'Bahan baku berhasil diperbarui.');
    }
}
