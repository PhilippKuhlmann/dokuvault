<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Den Code der zweiten Stufe bei der Anmeldung pruefen - im Browser und in der App.
 *
 * Stand vorher privat in TwoFactorChallengeController. Die App meldet sich
 * ohne Sitzung an und braucht dieselbe Pruefung mit derselben Bremse: Zwei
 * Eingaenge mit getrennten Zaehlern waeren doppelt so viele Versuche.
 */
class ZweiteStufePruefung
{
    public function __construct(private ZweiteStufe $zweiteStufe) {}

    /**
     * Prueft den Einmal- oder Wiederherstellungscode.
     *
     * @return bool ob ein Wiederherstellungscode verbraucht wurde
     *
     * @throws ValidationException bei leerem, falschem oder gebremstem Code
     */
    public function pruefen(User $nutzer, string $eingabe, string $ip): bool
    {
        $this->nichtGesperrt($nutzer, $ip);

        $eingabe = trim($eingabe);

        if ($eingabe === '') {
            throw ValidationException::withMessages(['code' => __('Bitte einen Code eingeben.')]);
        }

        // Erst der Einmalcode, dann der Wiederherstellungscode: Ein
        // sechsstelliger Code aus der App kann kein Wiederherstellungscode
        // sein, und andersherum genauso - die Reihenfolge kostet also nichts
        // und spart eine Zweitunterscheidung an der Eingabe.
        $mitCode = $this->zweiteStufe->stimmt($nutzer->two_factor_secret, $eingabe);
        $mitZettel = ! $mitCode && $nutzer->wiederherstellungscodeVerbrauchen($eingabe);

        if (! $mitCode && ! $mitZettel) {
            RateLimiter::hit($this->schluessel($nutzer, $ip), Setting::anmeldungSperreSekunden());

            // Ein falscher zweiter Faktor loest kein Auth\Events\Failed aus -
            // das Kennwort stimmte ja. Genau dieser Fall ist aber der
            // interessante: Jemand kennt das Kennwort und kommt trotzdem nicht
            // herein.
            activity()
                ->event('anmeldung_gescheitert')
                ->performedOn($nutzer)
                ->causedBy($nutzer)
                ->withProperties([
                    'objekt' => $nutzer->name,
                    'attributes' => array_filter([
                        'Schritt' => __('Zweite Stufe'),
                        'IP' => $ip,
                    ]),
                ])
                ->log('Anmeldung gescheitert');

            throw ValidationException::withMessages([
                'code' => __('Der Code stimmt nicht.'),
            ]);
        }

        RateLimiter::clear($this->schluessel($nutzer, $ip));

        return $mitZettel;
    }

    private function schluessel(User $nutzer, string $ip): string
    {
        return 'zweite-stufe|'.$nutzer->id.'|'.$ip;
    }

    /**
     * Ohne diese Bremse waere die zweite Stufe sechs Ziffern, die sich in
     * Ruhe durchprobieren lassen - eine Million Moeglichkeiten sind schnell
     * durch, wenn niemand mitzaehlt.
     *
     * Dieselben Zahlen wie bei der Anmeldung, und jetzt auch dieselbe Quelle:
     * Sie standen hier ein zweites Mal, mit einem Kommentar, der behauptete,
     * es seien dieselben. Ein Kommentar haelt zwei Zahlen nicht zusammen.
     */
    private function nichtGesperrt(User $nutzer, string $ip): void
    {
        if (! RateLimiter::tooManyAttempts($this->schluessel($nutzer, $ip), Setting::anmeldungVersuche())) {
            return;
        }

        $sekunden = RateLimiter::availableIn($this->schluessel($nutzer, $ip));

        throw ValidationException::withMessages([
            'code' => trans('auth.throttle', [
                'seconds' => $sekunden,
                'minutes' => ceil($sekunden / 60),
            ]),
        ]);
    }
}
