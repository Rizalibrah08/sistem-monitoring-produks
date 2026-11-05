<?php

namespace App\Http\Controllers\Auth;

use App\Actions\AuthenticateStaff;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request, AuthenticateStaff $authenticate): RedirectResponse
    {
        $user = $authenticate->handle($request->validated('email'), $request->validated('password'));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('last_activity_at', now()->timestamp);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        try {
            $audit->record('auth.logout', $request->user());
        } finally {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login');
    }
}
