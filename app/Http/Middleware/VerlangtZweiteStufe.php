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

    public function handle(Request $request, Closure $next): Response
    {
        $nutzer = $request->user();

        if (! $nutzer || ! $nutzer->mussZweiteStufeEinrichten()) {
            return $next($request);
        }

        // Livewire laeuft ueber eine eigene Route. Sie hier zu sperren hiesse,
        // auch das Profilformular lahmzulegen - der Kopierknopf und die
        // Sprachumschaltung haengen daran. Die Seiten dahinter sind ohnehin
        // gesperrt, weil man sie nicht aufrufen kann.
        if ($request->routeIs('livewire.*')) {
            return $next($request);
        }

        if ($request->routeIs(self::OFFEN)) {
            return $next($request);
        }

        return redirect()->route('profile.edit')->withFragment('zweite-stufe');
    }
}
