<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. If not logged in, proceed
        if (! auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // 2. Superadmin is exempt from subscription checks
        if ($user->role === 'superadmin') {
            return $next($request);
        }

        // 3. Prevent infinite redirect loop on safe routes
        $safeRoutes = [
            'subscription.expired',
            'logout',
            'lang.switch',
        ];

        if ($request->routeIs($safeRoutes)) {
            return $next($request);
        }

        // 4. Fetch company and check subscription
        $company = $user->company;

        if ($company) {
            // Check if subscription has ended
            if ($company->subscription_ends_at && now()->gt($company->subscription_ends_at)) {
                return redirect()->route('subscription.expired');
            }
        }

        return $next($request);
    }
}
