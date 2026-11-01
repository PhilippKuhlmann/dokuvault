<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Whoever has to set up the second factor gets no further than their own profile.
 *
 * Deliberately a redirect and not a 403: the user has done nothing wrong,
 * they are missing a step - and that step is exactly where they are sent.
 *
 *
 * In the "web" group so that it applies to Livewire too: otherwise every
 * list and every form of this application would still be reachable through
 * /livewire/update, and the requirement would be a mere display.
 *
 * Also on the auth:sanctum groups of the API: a token issued before the
 * second factor was required would otherwise keep working. There the answer
 * is a 403 - an app or script cannot set up the second factor anyway.
 */
class RequireTwoFactor
{
    /**
     * Paths that have to stay open - otherwise the user would not even get
     * to where they set up the second factor.
     */
    private const OFFEN = [
        'profile.edit',
        'two-factor.begin',
        'two-factor.discard',
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

        if (! $nutzer || ! $nutzer->mustEnrollTwoFactor()) {
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

        return redirect()->route('profile.edit')->withFragment('two-factor');
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
