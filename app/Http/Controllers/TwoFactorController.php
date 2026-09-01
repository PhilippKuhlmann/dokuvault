<?php

namespace App\Http\Controllers;

use App\Support\ZweiteStufe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

/**
 * Die zweite Stufe im eigenen Profil einrichten, bestaetigen und abschalten.
 *
 * Der Ablauf hat bewusst zwei Schritte: "beginnen" legt ein Geheimnis an und
 * zeigt QR-Code und Klartext, "bestaetigen" nimmt es erst in Betrieb, wenn ein
 * Code daraus stimmt. Wer sein Telefon falsch eingerichtet hat, merkt es hier
 * und nicht bei der naechsten Anmeldung.
 */
class TwoFactorController extends Controller
{
    /** Wo das noch unbestaetigte Geheimnis liegt, solange es nicht gilt. */
    public const IN_ARBEIT = 'zweite-stufe.geheimnis';

    public function __construct(private ZweiteStufe $zweiteStufe)
    {
        $this->middleware(['auth']);
    }

    /**
     * Einrichtung beginnen: Geheimnis erzeugen und in der Sitzung halten. In
     * der Datenbank hat es erst etwas zu suchen, wenn es bestaetigt ist -
     * sonst stuende dort ein Geheimnis, das niemand kennt.
     */
    public function begin(Request $request): RedirectResponse
    {
        if ($fehler = $this->demoGesperrt($request)) {
            return $fehler;
        }

        $request->session()->put(self::IN_ARBEIT, $this->zweiteStufe->geheimnisErzeugen());

        return Redirect::route('profile.edit')->withFragment('zweite-stufe');
    }

    /** Einrichtung abbrechen, ohne etwas zu ändern. */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::IN_ARBEIT);

        return Redirect::route('profile.edit')->withFragment('zweite-stufe');
    }

    /**
     * Bestaetigen: erst wenn ein Code aus der App stimmt, wird das Geheimnis
     * gespeichert - zusammen mit den Wiederherstellungscodes.
     */
    public function confirm(Request $request): RedirectResponse
    {
        if ($fehler = $this->demoGesperrt($request)) {
            return $fehler;
        }

        $geheimnis = $request->session()->get(self::IN_ARBEIT);

        if (! $geheimnis) {
            return Redirect::route('profile.edit')
                ->withErrors(['code' => __('Die Einrichtung wurde nicht begonnen oder ist abgelaufen.')], 'zweiteStufe')
                ->withFragment('zweite-stufe');
        }

        $request->validateWithBag('zweiteStufe', ['code' => ['required', 'string']]);

        if (! $this->zweiteStufe->stimmt($geheimnis, $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => __('Der Code stimmt nicht. Steht in der App die richtige Uhrzeit?'),
            ])->errorBag('zweiteStufe');
        }

        $codes = $this->zweiteStufe->wiederherstellungscodes();

        $request->user()->forceFill([
            'two_factor_secret' => $geheimnis,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget(self::IN_ARBEIT);

        // Einmal anzeigen, danach nie wieder: Sie liegen verschluesselt, aber
        // sie noch einmal herzuzeigen hiesse, sie noch einmal preiszugeben.
        return Redirect::route('profile.edit')
            ->with('zweite-stufe-codes', $codes)
            ->withFragment('zweite-stufe');
    }

    /**
     * Neue Wiederherstellungscodes. Braucht das Kennwort: Wer an einem
     * offenen Bildschirm sitzt, soll sich nicht acht Dauerzugaenge ausdrucken
     * koennen.
     */
    public function regenerate(Request $request): RedirectResponse
    {
        if ($fehler = $this->demoGesperrt($request)) {
            return $fehler;
        }

        $request->validateWithBag('zweiteStufe', [
            'password' => ['required', 'current_password'],
        ]);

        $codes = $this->zweiteStufe->wiederherstellungscodes();

        $request->user()->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return Redirect::route('profile.edit')
            ->with('zweite-stufe-codes', $codes)
            ->withFragment('zweite-stufe');
    }

    /** Abschalten - ebenfalls nur mit Kennwort. */
    public function destroy(Request $request): RedirectResponse
    {
        if ($fehler = $this->demoGesperrt($request)) {
            return $fehler;
        }

        // Verlangt der Administrator sie, laesst sie sich nicht abschalten -
        // sonst waere die Pflicht ein Knopf, den man einmal drueckt.
        if ($request->user()->two_factor_required) {
            return Redirect::route('profile.edit')
                ->withErrors(['password' => __('Ihr Administrator verlangt die zweite Stufe. Sie lässt sich nicht abschalten.')], 'zweiteStufeAus')
                ->withFragment('zweite-stufe');
        }

        $request->validateWithBag('zweiteStufeAus', [
            'password' => ['required', 'current_password'],
        ]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget(self::IN_ARBEIT);

        return Redirect::route('profile.edit')->withFragment('zweite-stufe');
    }

    /**
     * Siehe User::istDemoGeschuetzt(): Auf der Demo teilen sich alle Besucher
     * einen Zugang. Wer dort eine zweite Stufe einrichtet, sperrt alle
     * uebrigen aus.
     */
    private function demoGesperrt(Request $request): ?RedirectResponse
    {
        if (! $request->user()->istDemoGeschuetzt()) {
            return null;
        }

        return Redirect::route('profile.edit')
            ->withErrors(['demo' => __('Dieser Demo-Zugang ist gesperrt und lässt sich nicht ändern.')], 'zweiteStufe')
            ->withFragment('zweite-stufe');
    }
}
