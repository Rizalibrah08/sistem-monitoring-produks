<?php

namespace App\Http\Controllers;

use App\Actions\SaveMasterRecord;
use App\Http\Requests\SaveStageRequest;
use App\Models\Stage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class StageController extends Controller
{
    public function store(SaveStageRequest $request, SaveMasterRecord $save): RedirectResponse
    {
        $attributes = [...$request->validated(), 'code' => $this->uniqueCode($request->string('name')->toString())];
        $save->handle(new Stage, 'stage', $attributes, $request->user());

        return back()->with('status', 'Tahap berhasil ditambahkan.');
    }

    public function update(SaveStageRequest $request, Stage $stage, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle($stage, 'stage', $request->validated(), $request->user());

        return back()->with('status', 'Tahap berhasil diperbarui.');
    }

    /** Kode dibuat sekali dari nama saat tahap ditambahkan dan tidak berubah setelahnya. */
    private function uniqueCode(string $name): string
    {
        $base = Str::limit(Str::slug($name, '_'), 50, '') ?: 'tahap';
        $code = $base;

        for ($suffix = 2; Stage::query()->where('code', $code)->exists(); $suffix++) {
            $code = "{$base}_{$suffix}";
        }

        return $code;
    }
}
