<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wer die zweite Stufe einrichten muss, kommt bis dahin nur ins eigene Profil.
 *
 * Bewusst als Umleitung und nicht als 403: Der Nutzer hat nichts falsch
 * gemacht, ihm fehlt ein Schritt - und der steht genau dort, wohin er
 * geschickt wird.
 *
 * In der Gruppe "web", damit es auch fuer Livewire gilt: Sonst waere jede
 * Liste und jedes Formular dieser Anwendung ueber /livewire/update weiterhin
 * erreichbar, und die Pflicht waere eine Anzeige.
 *
 * Also on the auth:sanctum groups of the API: a token issued before the
 * second factor was required would otherwise keep working. There the answer
 * is a 403 - an app or script cannot set up the second factor anyway.
 */
class VerlangtZweiteStufe
{
    /**
     * Wege, die offen bleiben muessen - sonst kaeme der Nutzer nicht einmal
     * dorthin, wo er die zweite Stufe einrichtet.
     */
    private const OFFEN = [
        'profile.edit',
        'two-factor.begin',
        'two-factor.verwerfen',
        'two-factor.confirm',
        'logout',
        'locale.update',
        'branding.logo',
        'changelog',
    ];

    /** Livewire components on the profile page (profile/edit.blade.php). */
    private const PROFIL_KOMPONENTEN = [
        'notification-profile',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $nutzer = $request->user();

        if (! $nutzer || ! $nutzer->mussZweiteStufeEinrichten()) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            abort(403, __('Ihr Administrator verlangt die zweite Stufe. Richten Sie sie zuerst im Browser in Ihrem Profil ein.'));
        }

        // Livewire runs over a route of its own. Only the components of the
        // profile page get through: a snapshot from a page loaded before the
        // requirement was set would otherwise still work - a password field
        // could reveal its value without the second factor.
        // Livewire 4 names the route default-livewire.update, hence the wildcard.
        if ($request->routeIs('*livewire.*')) {
            abort_unless($this->nurProfilKomponenten($request), 403, __('Ihr Administrator verlangt die zweite Stufe. Richten Sie sie zuerst in Ihrem Profil ein.'));

            return $next($request);
        }

        if ($request->routeIs(self::OFFEN)) {
            return $next($request);
        }

        return redirect()->route('profile.edit')->withFragment('zweite-stufe');
    }

    /**
     * Whether every component of this Livewire update belongs to the profile
     * page. The name sits in the snapshot; the snapshot is signed, so it
     * cannot be renamed - Livewire rejects a changed one itself. Anything
     * other than an update (file upload) is refused.
     */
    private function nurProfilKomponenten(Request $request): bool
    {
        $komponenten = $request->input('components');

        if (! $request->routeIs('*livewire.update') || ! is_array($komponenten) || $komponenten === []) {
            return false;
        }

        foreach ($komponenten as $komponente) {
            $snapshot = json_decode((string) ($komponente['snapshot'] ?? ''), true);

            if (! in_array($snapshot['memo']['name'] ?? null, self::PROFIL_KOMPONENTEN, true)) {
                return false;
            }
        }

        return true;
    }
}
