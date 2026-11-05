<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use App\StaffRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveStaffSession
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $lastActivity = $request->session()->get('last_activity_at', now()->timestamp);
        $event = null;

        if (! $user->is_active || ! $user->hasAnyRole(StaffRole::cases())) {
            $event = 'auth.access_revoked';
        } elseif (now()->timestamp - (int) $lastActivity >= (int) config('session.lifetime') * 60) {
            $event = 'auth.session_expired';
        }

        if ($event !== null) {
            try {
                $this->audit->record($event, $user, actor: $user);
            } finally {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->with('status', 'Sesi berakhir. Silakan masuk kembali.');
        }

        $request->session()->put('last_activity_at', now()->timestamp);

        return $next($request);
    }
}
