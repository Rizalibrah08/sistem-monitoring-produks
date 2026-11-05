<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCriteriaRequest;
use App\Models\Criterion;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CriterionController extends Controller
{
    public function update(SaveCriteriaRequest $request, string $queue, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $queue, $audit): void {
            $criteria = Criterion::query()->where('queue', $queue)->lockForUpdate()->get()->keyBy('code');
            abort_unless($criteria->count() === count(Criterion::CODES), 404);
            $before = [];
            $after = [];

            foreach ($request->validated('criteria') as $code => $values) {
                $criterion = $criteria->get($code);
                $before[$code] = ['weight' => $criterion->weight, 'type' => $criterion->type->value];
                $criterion->fill(['weight' => (float) $values['weight'], 'type' => $values['type']])->save();
                $after[$code] = ['weight' => $criterion->weight, 'type' => $criterion->type->value];
            }

            $audit->record('master.criteria.updated', before: $before, after: $after, actor: $request->user(), properties: ['queue' => $queue]);
        });

        return back()->with('status', 'Bobot kriteria antrean '.Criterion::queueLabel($queue).' berhasil diperbarui.');
    }
}
