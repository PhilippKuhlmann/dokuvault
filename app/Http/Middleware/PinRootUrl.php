<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every link the application builds starts with APP_URL - never with the Host
 * the request happened to name.
 *
 * Without this, "forgot password" built the mail link from the Host header:
 * a request with the attacker's domain as Host sent the victim a genuine mail
 * whose link handed the reset token to the attacker. Mails, redirects and
 * invitations all go through the same URL generator, so pinning it here
 * covers them all.
 *
 * Per request rather than once at boot, so the configured value is read
 * where it is used.
 */
class PinRootUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $root = rtrim((string) config('app.url'), '/');

        if ($root !== '') {
            URL::forceRootUrl($root);
            // The generator puts the request's scheme back onto a forced
            // root - pin that too, or an http request yields http links.
            URL::forceScheme(parse_url($root, PHP_URL_SCHEME) ?: 'https');
        }

        return $next($request);
    }
}
