<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ein gesperrter Zugang kommt nicht weiter - auch nicht mit einer Sitzung, die
 * vor der Sperre begonnen hat.
 *
 * Ohne das waere "deaktiviert" eine halbe Massnahme: Die Anmeldung waere zu,
 * aber wer gerade angemeldet ist, bliebe drin, bis er sich selbst abmeldet -
 * unter Umstaenden tagelang. Genau der Fall, in dem gesperrt wird (jemand
 * verlaesst das Haus), ist der, in dem das nicht reichen darf.
 *
 * Steht in der Gruppe "web" und an den auth:sanctum-Gruppen der API: Beide
 * Wege fuehren ueber auth()->user(), und ein Script mit dem Token des
 * Gesperrten soll ebenso wenig weiterarbeiten wie sein Browser. Die
 * Agent-Token haengen an keinem Benutzer und bleiben unberuehrt - sie
 * dokumentieren Geraete, nicht Personen.
 */
class ZugangAktiv
{
    public function handle(Request $request, Closure $next): Response
    {
        $nutzer = $request->user();

        if ($nutzer === null || ! $nutzer->istDeaktiviert()) {
            return $next($request);
        }

        // Ueber ein Token gekommen: Es gibt keine Sitzung zum Beenden, und ein
        // Script kann mit einer Weiterleitung nichts anfangen.
        if ($request->expectsJson() || $request->routeIs('api.*') || $request->is('api/*')) {
            abort(403, __('Dieser Zugang ist deaktiviert.'));
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->withErrors(['username' => __('Dieser Zugang ist deaktiviert. Wenden Sie sich an Ihre Administration.')]);
    }
}
