<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEventFeature
{
    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $event = $request->route('event');
        $user = $request->user();

        if ($user && $user->hasRole('admin')) {
            return $next($request);
        }

        if (! $event) {
            return $next($request);
        }

        // Cross-company access is a plain authorization failure, not a billing
        // one — deny it outright rather than redirecting to another company's
        // billing page (which would itself 403, but only after leaking a redirect).
        abort_if(! $user || $user->company_id !== $event->company_id, 403);

        if ($event->hasFeature($featureKey)) {
            return $next($request);
        }

        return redirect()->route('events.billing.show', $event)
            ->with('error', 'This feature isn\'t part of this event\'s paid bill yet. Add it and pay to unlock it.');
    }
}
