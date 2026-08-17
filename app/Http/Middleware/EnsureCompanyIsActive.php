<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasRole('admin') || ! $user->company_id) {
            return $next($request);
        }

        $company = $user->company;

        abort_if(! $company->is_active, 403, 'Your company account has been suspended.');

        if ($company->subscription_ends_at && $company->subscription_ends_at->endOfDay()->isPast()) {
            return redirect()->route('billing.index')
                ->with('error', 'Your subscription has expired. Renew it to restore event access.');
        }

        return $next($request);
    }
}
