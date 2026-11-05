<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $accounts = null;

        if ($request->user()->can('users.manage')) {
            $summary = User::query()->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active')->first();
            $accounts = [
                'total' => (int) $summary->total,
                'active' => (int) $summary->active,
                'inactive' => (int) $summary->total - (int) $summary->active,
                'activities_today' => Activity::query()->where('created_at', '>=', today())->count(),
            ];
        }

        return Inertia::render('Backend/Dashboard', [
            'accounts' => $accounts,
            'today' => now()->locale('id')->translatedFormat('l, d F Y'),
        ]);
    }
}
