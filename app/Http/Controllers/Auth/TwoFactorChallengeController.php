<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\LeitetNachAnmeldungWeiter;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ZweiteStufe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Der zweite Schritt der Anmeldung.
 *
 * Zwischen Kennwort und Einmalcode ist der Nutzer nicht angemeldet - in der
 * Sitzung steht nur, wer sich anmelden moechte. Wer hier stehenbleibt, kommt
 * nirgendwo hin.
 */
class TwoFactorChallengeController extends Controller
{
    use LeitetNachAnmeldungWeiter;

    /** Wer auf den Einmalcode wartet, und ob er angemeldet bleiben wollte. */
    public const WARTET = 'zweite-stufe.wartet';

    public const GEMERKT = 'zweite-stufe.gemerkt';

    /** Versuche und Sperrdauer wie bei der Anmeldung selbst. */
    private const VERSUCHE = 5;

    private const SPERRE = 900;

    public function __construct(private ZweiteStufe $zweiteStufe) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->wartenderNutzer($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $nutzer = $this->wartenderNutzer($request);

        if (! $nutzer) {
            return redirect()->route('login');
        }

        $this->nichtGesperrt($nutzer);

        $eingabe = trim((string) $request->input('code'));

        if ($eingabe === '') {
            throw ValidationException::withMessages(['code' => __('Bitte einen Code eingeben.')]);
        }

        // Erst der Einmalcode, dann der Wiederherstellungscode: Ein
        // sechsstelliger Code aus der App kann kein Wiederherstellungscode
        // sein, und andersherum genauso - die Reihenfolge kostet also nichts
        // und spart eine Zweitunterscheidung an der Eingabe.
        $stimmt = $this->zweiteStufe->stimmt($nutzer->two_factor_secret, $eingabe)
            || $nutzer->wiederherstellungscodeVerbrauchen($eingabe);

        if (! $stimmt) {
            RateLimiter::hit($this->schluessel($nutzer), self::SPERRE);

            throw ValidationException::withMessages([
                'code' => __('Der Code stimmt nicht.'),
            ]);
        }

        RateLimiter::clear($this->schluessel($nutzer));

        $gemerkt = (bool) $request->session()->get(self::GEMERKT);

        $request->session()->forget([self::WARTET, self::GEMERKT]);

        Auth::login($nutzer, $gemerkt);

        $request->session()->regenerate();

        // Siehe AuthenticatedSessionController: regenerate() behaelt die
        // Daten, ein alter Kennwort-Hash wuerde eine Anmeldeschleife ergeben.
        $request->session()->forget('password_hash_web');

        return $this->nachDerAnmeldung();
    }

    /**
     * Abbrechen - der wartende Nutzer wird vergessen, sonst haengt die
     * Sitzung bis zum Ablauf an einer halben Anmeldung.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget([self::WARTET, self::GEMERKT]);

        return redirect()->route('login');
    }

    private function wartenderNutzer(Request $request): ?User
    {
        $id = $request->session()->get(self::WARTET);

        return $id ? User::find($id) : null;
    }

    private function schluessel(User $nutzer): string
    {
        return 'zweite-stufe|'.$nutzer->id.'|'.request()->ip();
    }

    /**
     * Ohne diese Bremse waere die zweite Stufe sechs Ziffern, die sich in
     * Ruhe durchprobieren lassen - eine Million Moeglichkeiten sind schnell
     * durch, wenn niemand mitzaehlt.
     */
    private function nichtGesperrt(User $nutzer): void
    {
        if (! RateLimiter::tooManyAttempts($this->schluessel($nutzer), self::VERSUCHE)) {
            return;
        }

        $sekunden = RateLimiter::availableIn($this->schluessel($nutzer));

        throw ValidationException::withMessages([
            'code' => trans('auth.throttle', [
                'seconds' => $sekunden,
                'minutes' => ceil($sekunden / 60),
            ]),
        ]);
    }
}
