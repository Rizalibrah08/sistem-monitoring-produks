<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $configuration = config('workspace.modules.'.$module);
        abort_if($configuration === null, 404);
        abort_unless($request->user()?->hasAnyRole($configuration['roles']), 403);

        return $next($request);
    }
}
