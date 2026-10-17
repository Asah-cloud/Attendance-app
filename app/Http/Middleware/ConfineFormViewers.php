<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps an account that exists only to see shared form responses on those pages. Signed-in
 * pages other than its shared forms, its profile/password and signing out are refused (a
 * page visit is sent back to the shared forms list). Public pages are unaffected.
 */
class ConfineFormViewers
{
    private const ALLOWED = ['shared-forms.*', 'profile.edit', 'profile.update', 'password.*', 'logout', 'verification.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();

        if (! $user || ! $route || ! in_array('auth', $route->gatherMiddleware(), true)
            || $request->routeIs(...self::ALLOWED) || ! $user->isFormViewerOnly()) {
            return $next($request);
        }

        abort_unless($request->isMethod('GET'), 403);

        return redirect()->route('shared-forms.index');
    }
}
