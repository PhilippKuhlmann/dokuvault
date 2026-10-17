<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\LeitetNachAnmeldungWeiter;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ZweiteStufePruefung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function __construct(private ZweiteStufePruefung $pruefung) {}

    public function create(Request $request): View|RedirectResponse
    {
        $nutzer = $this->wartenderNutzer($request);

        if (! $nutzer) {
            return redirect()->route('login');
        }

        if ($abweisung = $this->gesperrtAbweisen($request, $nutzer)) {
            return $abweisung;
        }

        return view('auth.two-factor-challenge');
    }

    /**
     * Ein gesperrter Zugang kommt auch durch den zweiten Eingang nicht herein.
     *
     * LoginRequest prueft die Sperre beim Kennwort. Wer zwischen Kennwort und
     * Einmalcode gesperrt wird, kaeme hier aber trotzdem durch: Auth::login()
     * weiter unten fragt nicht nach. Dass ZugangAktiv ihn beim naechsten
     * Aufruf wieder hinauswirft, ist kein Ersatz - bis dahin haette er einen
     * gueltigen "angemeldet bleiben"-Cookie, einen verbrauchten
     * Wiederherstellungscode und einen Protokolleintrag "Angemeldet".
     *
     * Deshalb vor der Codepruefung und nicht danach: Sonst ist der Zettel-Code
     * schon verbraucht, wenn die Sperre auffaellt.
     */
    private function gesperrtAbweisen(Request $request, User $nutzer): ?RedirectResponse
    {
        if (! $nutzer->istDeaktiviert()) {
            return null;
        }

        $request->session()->forget([self::WARTET, self::GEMERKT]);

        return redirect()->route('login')->withErrors([
            'username' => __('Dieser Zugang ist deaktiviert. Wenden Sie sich an Ihre Administration.'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $nutzer = $this->wartenderNutzer($request);

        if (! $nutzer) {
            return redirect()->route('login');
        }

        if ($abweisung = $this->gesperrtAbweisen($request, $nutzer)) {
            return $abweisung;
        }

        // Code, Bremse und Protokoll der Fehlversuche - dieselbe Pruefung wie
        // bei der Anmeldung aus der App, siehe ZweiteStufePruefung.
        $mitZettel = $this->pruefung->pruefen($nutzer, (string) $request->input('code'), $request->ip());

        $gemerkt = (bool) $request->session()->get(self::GEMERKT);

        $request->session()->forget([self::WARTET, self::GEMERKT]);

        Auth::login($nutzer, $gemerkt);

        $request->session()->regenerate();

        // Siehe AuthenticatedSessionController: regenerate() behaelt die
        // Daten, ein alter Kennwort-Hash wuerde eine Anmeldeschleife ergeben.
        $request->session()->forget('password_hash_web');

        if ($mitZettel) {
            $this->uebrigeCodesMelden($nutzer->fresh());
        }

        return $this->nachDerAnmeldung();
    }

    /**
     * Nach einem verbrauchten Wiederherstellungscode sagen, wie viele noch da
     * sind.
     *
     * Jeder gilt genau einmal. Wer nicht mitzaehlt, merkt beim naechsten
     * verlorenen Telefon, dass keiner mehr uebrig war - und kommt dann gar
     * nicht mehr herein.
     */
    private function uebrigeCodesMelden(User $nutzer): void
    {
        $uebrig = count($nutzer->two_factor_recovery_codes ?? []);

        // put statt flash: Ein Flash gilt genau einen Aufruf. Nach der
        // Anmeldung liegt aber oft noch eine Weiterleitung dazwischen - ein
        // Administrator wird von der Kundensuche in den Adminbereich
        // geschickt -, und der Hinweis waere dort schon verbraucht, ohne je
        // gezeigt worden zu sein. Die Ansicht raeumt ihn weg, sobald sie ihn
        // ausgegeben hat.
        session()->put('warnung', $uebrig === 0
            ? __('Das war Ihr letzter Wiederherstellungscode. Erzeugen Sie im Profil neue – sonst kommen Sie ohne Ihre App nicht mehr herein.')
            // Zwei Zeichenketten statt trans_choice: Bei einer Pluralform, die
            // auf Deutsch nicht hinterlegt ist, faellt Laravel auf die
            // Ersatzsprache zurueck - ein deutscher Nutzer bekaeme den
            // englischen Satz. Bei __() passiert das nicht.
            : ($uebrig === 1
                ? __('Wiederherstellungscode verbraucht. Es ist noch einer übrig – neue gibt es im Profil.')
                : __('Wiederherstellungscode verbraucht. Es sind noch :anzahl übrig.', ['anzahl' => $uebrig])));
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
}
