<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sprache fuer die laufende Sitzung umschalten.
 *
 * Bewusst ohne Anmeldung erreichbar: Auf der Anmeldeseite gibt es keinen
 * Nutzer, dessen Einstellung greifen koennte, und auf der Demo ist der
 * geteilte Zugang gesperrt. Die dauerhafte Wahl steht im Profil.
 */
class LocaleController extends Controller
{
    public function __construct()
    {
        // Kein auth-Middleware: siehe Klassenkommentar.
    }

    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('custom.locales', [])), 404);

        $request->session()->put('locale', $locale);

        return back();
    }
}
