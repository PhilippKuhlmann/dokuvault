<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Legt die Sprache je Anfrage fest.
 *
 * Reihenfolge: Einstellung des angemeldeten Nutzers, dann die Wahl in der
 * Sitzung (greift auf Gastseiten, etwa der Anmeldung), dann die Browsersprache,
 * zuletzt die Vorgabe aus config/app.php. Unbekannte Werte werden verworfen -
 * locale kommt sonst ungeprueft aus der Datenbank in App::setLocale().
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $erlaubt = array_keys(config('custom.locales', []));

        $sprache = collect([
            auth()->user()?->locale,
            $request->session()->get('locale'),
            $request->getPreferredLanguage($erlaubt),
        ])->first(fn ($wert) => $wert && in_array($wert, $erlaubt, true));

        App::setLocale($sprache ?? config('app.locale'));

        return $next($request);
    }
}
