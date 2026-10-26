<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The legacy REST API is for integrations: tokens an administrator creates
 * in the admin area (ability "*"). An app token ("mobil") is issued to
 * anyone who knows their own password - it must not open a second API with
 * different rules next to the one the app uses.
 */
class RequireIntegrationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && method_exists($token, 'can') && ! $token->can('*')) {
            abort(403, __('Dieser Token gilt nur für die App, nicht für die Schnittstelle.'));
        }

        return $next($request);
    }
}
