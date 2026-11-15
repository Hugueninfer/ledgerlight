<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the current tenant from the authenticated user, never from the request.
 * Must run before SubstituteBindings so route model binding resolves inside
 * the tenant scope (see the priority list in bootstrap/app.php).
 */
class SetCurrentTenant
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Be explicit when unauthenticated: never inherit a tenant from earlier work.
        if ($user === null) {
            $this->currentTenant->forget();
        } else {
            $tenant = $user->tenant;
            $tenant === null ? $this->currentTenant->forget() : $this->currentTenant->set($tenant);
        }

        return $next($request);
    }
}
