<?php

namespace App\Http\Controllers;

use App\Notifications\ZweiteStufeCode;
use App\Notifications\ZweiteStufeEingerichtet;
use App\Support\ZweiteStufe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Throwable;

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

    /** The mail code for this setup: its hash and until when it holds. */
    public const MAILCODE = 'zweite-stufe.mailcode';

    /** How long the mail code holds, in minutes. */
    public const MAILCODE_MINUTEN = 15;

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

        $request->session()->forget(self::MAILCODE);

        // With an email address the setup also needs a code from the mail:
        // the password alone would let anyone who knows it set up their own
        // app and lock the owner out. Without an address there is no second
        // way to reach the owner - the info mail after setup is skipped too.
        if ($request->user()->email) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            try {
                $request->user()->notify(new ZweiteStufeCode($code));
            } catch (Throwable $fehler) {
                report($fehler);

                return Redirect::route('profile.edit')
                    ->withErrors(['einrichtung' => __('Die Mail mit dem Bestätigungscode ließ sich nicht senden. Bitte wenden Sie sich an Ihre Administration.')], 'zweiteStufe')
                    ->withFragment('zweite-stufe');
            }

            $request->session()->put(self::MAILCODE, [
                'hash' => $this->mailcodeHash($code),
                'bis' => now()->addMinutes(self::MAILCODE_MINUTEN)->timestamp,
            ]);
        }

        $request->session()->put(self::IN_ARBEIT, $this->zweiteStufe->geheimnisErzeugen());

        return Redirect::route('profile.edit')->withFragment('zweite-stufe');
    }

    private function mailcodeHash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /**
     * Checks the mail code, if this user needs one. A setup begun without
     * one (no address back then) has to start over once there is one.
     */
    private function mailcodePruefen(Request $request): void
    {
        if (! $request->user()->email) {
            return;
        }

        $erwartet = $request->session()->get(self::MAILCODE);
        $eingabe = preg_replace('/\s+/', '', (string) $request->input('mail_code'));

        if (! $erwartet || $erwartet['bis'] < now()->timestamp) {
            throw ValidationException::withMessages([
                'mail_code' => __('Der Code aus der Mail ist abgelaufen. Bitte die Einrichtung abbrechen und neu beginnen.'),
            ])->errorBag('zweiteStufe');
        }

        if (! hash_equals($erwartet['hash'], $this->mailcodeHash($eingabe))) {
            throw ValidationException::withMessages([
                'mail_code' => __('Der Code aus der Mail stimmt nicht.'),
            ])->errorBag('zweiteStufe');
        }
    }

    /** Einrichtung abbrechen, ohne etwas zu ändern. */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget([self::IN_ARBEIT, self::MAILCODE]);

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

        $this->mailcodePruefen($request);

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
            'two_factor_last_step' => null,
        ])->save();

        $request->session()->forget([self::IN_ARBEIT, self::MAILCODE]);

        if ($request->user()->email) {
            try {
                $request->user()->notify(new ZweiteStufeEingerichtet);
            } catch (Throwable $fehler) {
                // The setup itself worked - a failed info mail must not undo it.
                report($fehler);
            }
        }

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
            'two_factor_last_step' => null,
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
