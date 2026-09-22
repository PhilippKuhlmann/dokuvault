<?php

/**
 * Die Schutz-Header stehen auf jeder Antwort - auch auf einer, die niemanden
 * angemeldet hat. Geprüft wird an /login, weil die Seite ohne Zugang erreichbar
 * ist und die Middleware global läuft.
 */
test('die Schutz-Header stehen auf der Antwort', function () {
    $antwort = $this->get('/login');

    $antwort->assertOk();
    $antwort->assertHeader('X-Content-Type-Options', 'nosniff');
    $antwort->assertHeader('X-Frame-Options', 'DENY');
    $antwort->assertHeader('Referrer-Policy', 'same-origin');
    expect($antwort->headers->get('Permissions-Policy'))->toContain('camera=()');
});

test('die CSP verbietet fremde Herkünfte und das Einrahmen', function () {
    $csp = $this->get('/login')->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("form-action 'self'")
        ->toContain("base-uri 'self'")
        ->toContain("object-src 'none'");
});

test('script-src trägt eine Nonce statt unsafe-inline', function () {
    $csp = $this->get('/login')->headers->get('Content-Security-Policy');

    preg_match('/script-src ([^;]+)/', $csp, $treffer);
    $scriptSrc = $treffer[1] ?? '';

    expect($scriptSrc)->toContain("'nonce-")
        ->and($scriptSrc)->toContain("'unsafe-eval'")        // Alpine wertet zur Laufzeit aus
        ->and($scriptSrc)->not->toContain("'unsafe-inline'"); // eingeschleustes Inline-JS bleibt draußen
});

test('das Inline-Skript der Seite trägt dieselbe Nonce wie die CSP', function () {
    $antwort = $this->get('/login');
    $csp = $antwort->headers->get('Content-Security-Policy');

    preg_match("/'nonce-([^']+)'/", $csp, $treffer);
    $nonce = $treffer[1] ?? null;

    // Die Nonce aus dem Header muss am Inline-Skript stehen, sonst liefe es
    // nicht - der Beleg, dass Header und Seite dieselbe tragen.
    expect($nonce)->not->toBeNull();
    $antwort->assertSee('nonce="'.$nonce.'"', false);
});

test('ausserhalb der lokalen Umgebung steht kein Entwicklungsserver in der CSP', function () {
    // Die Testumgebung ist nicht "local" - die Vite-Ausnahme darf hier fehlen,
    // sonst stünde sie auch in Produktion.
    $csp = $this->get('/login')->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('5173');
});
