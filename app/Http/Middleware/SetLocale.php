<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Legt die Sprache je Anfrage fest.
 *
 * Reihenfolge: Einstellung des angemeldeten Nutzers, dann die Wahl in der
 * Sitzung (greift auf Gastseiten, etwa der Anmeldung), dann die Browsersprache,
 * zuletzt die Sprache der Installation (Einstellungen > Allgemein). Unbekannte Werte werden verworfen -
 * locale kommt sonst ungeprueft aus der Datenbank in App::setLocale().
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $erlaubt = array_keys(config('custom.locales', []));

        // Nicht getPreferredLanguage(): Das liefert bei unbekannter
        // Browsersprache die erste aus der uebergebenen Liste statt null - die
        // Stufe darunter kam damit nie zum Zug. Das fiel nicht auf, solange
        // "erste erlaubte" und "Vorgabe" dieselbe Sprache waren; seit sie
        // einstellbar ist, waere die Einstellung wirkungslos gewesen.
        $vomBrowser = collect($request->getLanguages())
            ->map(fn ($sprache) => strtolower(explode('_', $sprache)[0]))
            ->first(fn ($sprache) => in_array($sprache, $erlaubt, true));

        $sprache = collect([
            auth()->user()?->locale,
            $request->session()->get('locale'),
            $vomBrowser,
        ])->first(fn ($wert) => $wert && in_array($wert, $erlaubt, true));

        // Die letzte Stufe ist die Einstellung der Installation, nicht mehr
        // config/app.php - siehe Setting::sprache(), die unbekannte Werte
        // ohnehin verwirft.
        App::setLocale($sprache ?? Setting::sprache());

        return $next($request);
    }
}
