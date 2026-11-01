<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Determines the language per request.
 *
 * Order: the setting of the signed-in user, then the choice in the session
 * (which applies on guest pages such as the sign-in), then the browser
 * language, lastly the language of the installation (Einstellungen >
 * Allgemein). Unknown values are dropped - locale would otherwise reach
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $erlaubt = array_keys(config('custom.locales', []));

        // Not getPreferredLanguage(): with an unknown browser language that
        // returns the first from the list passed in instead of null - so the
        // stage below it never came into play. That went unnoticed as long as
        // "first allowed" and "default" were the same language; since it is
        // configurable, the setting would have had no effect.
        $vomBrowser = collect($request->getLanguages())
            ->map(fn ($sprache) => strtolower(explode('_', $sprache)[0]))
            ->first(fn ($sprache) => in_array($sprache, $erlaubt, true));

        $sprache = collect([
            auth()->user()?->locale,
            // The app comes without a session - there user and browser language count
            $request->hasSession() ? $request->session()->get('locale') : null,
            $vomBrowser,
        ])->first(fn ($wert) => $wert && in_array($wert, $erlaubt, true));

        // The last stage is the setting of the installation, no longer
        // config/app.php - see Setting::language(), which drops unknown values
        // ohnehin verwirft.
        App::setLocale($sprache ?? Setting::sprache());

        return $next($request);
    }
}
