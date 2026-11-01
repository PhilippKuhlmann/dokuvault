<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schutz-Header auf jede Antwort.
 *
 * Four of them carry no risk and are always set: the browser should not guess
 * file types (nosniff), not let the page into someone else's frame
 * (clickjacking), not pass full URLs to foreign targets, and not offer
 * camera, microphone or location in the first place.
 *
 * HSTS comes on top as soon as https is in play (see handle).
 *
 * The Content-Security-Policy is the last one. Scripts only from our own
 * domain or with this request's nonce, without 'unsafe-eval': Alpine runs in
 * the CSP build and evaluates its expressions with a parser of its own
 * instead of new Function (resources/js/app.js). Plus frames, form targets
 * and base URL only from our own domain - injected HTML cannot send data to
 * a foreign server, and nobody frames the page.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // One nonce per request: it stands in the CSP and on our own inline
        // scripts (the theme switch) as well as - through Vite - on its tags.
        // Injected <script> does not carry it and gets blocked.
        // useCspNonce() legt sie zugleich in Vite ab; view()->share reicht sie
        // to the blades (including the asset-free error page).
        $nonce = Vite::useCspNonce();
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        // set(..., replace: false): Setzt etwa ein Controller schon bewusst
        // a value of its own (a narrower CSP for one page, say), that stands.
        //
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('X-Frame-Options', 'DENY', false);
        $response->headers->set('Referrer-Policy', 'same-origin', false);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()', false);
        $response->headers->set('Content-Security-Policy', $this->csp($nonce), false);

        // HSTS only where https is actually in use: the browser then refuses
        // http for a year, for this host and its subdomains. Sent on a secure
        // request and whenever APP_URL is https - behind a proxy that is not
        // in TRUSTED_PROXIES the request itself looks like http, and the
        // header would otherwise be missing exactly where it matters.
        //
        // HSTS_MAX_AGE=0 leaves it out: where the TLS endpoint sets the header
        // itself, a second one of ours would be the one the browser uses
        // (RFC 6797: the first wins) and could undercut the proxy's policy.
        $hsts = (int) config('custom.hsts_max_age');
        if ($hsts > 0 && ($request->secure() || str_starts_with((string) config('app.url'), 'https://'))) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.$hsts.'; includeSubDomains', false);
        }

        return $response;
    }

    /**
     * The policy as one line. Everything from our own domain; in local
     * operation the Vite dev server on top, or neither the script nor the
     * live reload would load there.
     */
    private function csp(string $nonce): string
    {
        // Kein 'unsafe-eval' mehr: Alpine laeuft im CSP-Build. Kein
        // 'unsafe-inline' - the nonce takes its place, so only our own inline
        // scripts run and none that was injected. Alpine's @click are real
        // listeners, not inline JS.
        $skript = "'self' 'nonce-{$nonce}'";
        $stil = "'self' 'unsafe-inline'";
        $verbindung = "'self'";

        if (app()->environment('local')) {
            $vite = 'http://localhost:5173 http://127.0.0.1:5173';
            $skript .= ' '.$vite;
            $stil .= ' '.$vite;
            $verbindung .= ' '.$vite.' ws://localhost:5173 ws://127.0.0.1:5173';
        }

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "style-src {$stil}",
            "script-src {$skript}",
            "connect-src {$verbindung}",
        ]);
    }
}
