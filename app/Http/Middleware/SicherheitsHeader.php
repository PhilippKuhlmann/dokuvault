<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schutz-Header auf jede Antwort.
 *
 * Vier davon sind ohne Risiko und stehen immer: Der Browser soll Dateitypen
 * nicht raten (nosniff), die Seite nicht in einen fremden Rahmen lassen
 * (Clickjacking), keine vollen Adressen an fremde Ziele weitergeben und
 * Kamera/Mikrofon/Standort gar nicht erst anbieten.
 *
 * Die Content-Security-Policy ist der fuenfte. Sie ist hier bewusst keine
 * strenge Skript-Positivliste: Alpine wertet Ausdruecke zur Laufzeit aus
 * ('unsafe-eval') und Livewire wie Alpine schreiben Handler inline
 * ('unsafe-inline') - eine strengere Regel legte die Oberflaeche lahm. Ihr Wert
 * liegt daher im Verbot fremder Herkuenfte: Skripte, Rahmen, Formularziele und
 * Basis-URL nur von der eigenen Domain. Damit kann eingeschleustes HTML keine
 * Daten an einen fremden Server schicken und die Seite niemand einrahmen.
 */
class SicherheitsHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // set(..., replace: false): Setzt etwa ein Controller schon bewusst
        // einen eigenen Wert (z. B. eine engere CSP fuer eine Seite), bleibt
        // der stehen.
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('X-Frame-Options', 'DENY', false);
        $response->headers->set('Referrer-Policy', 'same-origin', false);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()', false);
        $response->headers->set('Content-Security-Policy', $this->csp(), false);

        return $response;
    }

    /**
     * Die Richtlinie als eine Zeile. Alles von der eigenen Domain; im lokalen
     * Betrieb zusaetzlich der Vite-Entwicklungsserver, sonst laedt dort weder
     * das Skript noch der Live-Reload.
     */
    private function csp(): string
    {
        $skript = "'self' 'unsafe-inline' 'unsafe-eval'";
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
