<?php

namespace App\Http\Middleware;

use App\Support\InitialSetup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * As long as the first administrator is missing, every page leads to /setup.
 * Without a setup code (existing installations, demo, tests) this costs one
 * look into the cached settings and nothing else.
 */
class RedirectToInitialSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('setup', 'setup.store') && InitialSetup::pending()) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
