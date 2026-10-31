<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Notifications\EmailChanged;
use App\Notifications\ZweiteStufeCode;
use App\Notifications\ZweiteStufeEingerichtet;
use App\Support\Changelog;
use App\Support\ZweiteStufe;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Das eigene Profil in der App: Name, E-Mail, Sprache und Kennwort.
 *
 * Dieselben Regeln wie im Browser - einschliesslich der, dass eine neue
 * E-Mail-Adresse das aktuelle Kennwort verlangt: Dort gehen die
 * Kennwort-Zuruecksetzungen hin, und eine geliehene Sitzung waere damit das
 * Konto.
 *
 * Nicht dabei: die zweite Stufe einrichten und das Konto loeschen. Beides
 * steht nur im Browser - das eine braucht einen QR-Code und den Code aus der
 * Mail, das andere ist nicht rueckgaengig zu machen.
 */
class ProfilController extends Controller
{
    public function zeigen(Request $request): JsonResponse
    {
        $nutzer = $request->user();

        return response()->json([
            'name' => $nutzer->name,
            'username' => $nutzer->username,
            'mail' => $nutzer->email,
            // null heisst "der Sprache des Geraets folgen", nicht
            // Leerstring.
            'sprache' => $nutzer->locale,
            'sprachen' => collect(config('custom.locales'))
                ->map(fn ($titel, $code) => ['code' => $code, 'titel' => $titel])
                ->values(),
            // Der Demo-Zugang ist geteilt: Was einer aendert, traefe alle.
            'gesperrt' => $nutzer->istDemoGeschuetzt(),
            'zweite_stufe' => $nutzer->two_factor_confirmed_at !== null,
        ]);
    }

    public function aendern(ProfileUpdateRequest $request): JsonResponse
    {
        $nutzer = $request->user();

        if ($nutzer->istDemoGeschuetzt()) {
            return response()->json(
                ['message' => __('Dieser Demo-Zugang ist gesperrt und lässt sich nicht ändern.')],
                422,
            );
        }

        $alteAdresse = $nutzer->email;

        $nutzer->fill(Arr::except($request->validated(), 'current_password'));
        $nutzer->save();

        // Nur ein echter Wechsel des Zuruecksetz-Ziels - nicht "Max@" zu
        // "max@".
        if (filled($alteAdresse) && mb_strtolower($alteAdresse) !== mb_strtolower((string) $nutzer->email)) {
            $this->alteAdresseBenachrichtigen($alteAdresse, $nutzer->name, (string) $nutzer->email);
        }

        return $this->zeigen($request);
    }

    /**
     * Ein neues Kennwort.
     *
     * Danach ist die App abgemeldet: User::booted() entzieht bei jedem
     * Kennwortwechsel alle App-Token - gewollt und an einer Stelle, damit
     * Zuruecksetzen, Profil, Admin-Bereich und Einladung alle dasselbe tun.
     * Deshalb sagt die Antwort es ausdruecklich ("neu_anmelden"), statt die
     * App in den naechsten 401 laufen zu lassen.
     */
    public function kennwort(Request $request): JsonResponse
    {
        $nutzer = $request->user();

        if ($nutzer->istDemoGeschuetzt()) {
            return response()->json(
                ['message' => __('Das Passwort eines Demo-Zugangs lässt sich nicht ändern.')],
                422,
            );
        }

        $geprueft = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $nutzer->update(['password' => Hash::make($geprueft['password'])]);

        return response()->json([
            'message' => __('Kennwort geändert. Bitte neu anmelden.'),
            'neu_anmelden' => true,
        ]);
    }

    /** Wie lange ein begonnener Einrichtungsvorgang gilt. */
    public const EINRICHTUNG_MINUTEN = 15;

    /**
     * Die zweite Stufe einrichten - Schritt eins.
     *
     * Die Mobil-Schnittstelle hat keine Sitzung, in der das begonnene
     * Geheimnis liegen koennte. Es geht deshalb verschluesselt als
     * "vorgang" mit hinaus und kommt beim Bestaetigen zurueck - dasselbe
     * Verfahren wie bei der Anmeldung mit zweiter Stufe.
     *
     * Mit E-Mail-Adresse braucht die Einrichtung zusaetzlich einen Code aus
     * der Mail: Das Kennwort allein liesse jeden, der es kennt, seine eigene
     * App einrichten und den Inhaber aussperren.
     */
    public function zweiteStufeBeginnen(Request $request, ZweiteStufe $zweiteStufe): JsonResponse
    {
        $nutzer = $request->user();

        if ($nutzer->istDemoGeschuetzt()) {
            return response()->json(['message' => __('Dieser Demo-Zugang ist gesperrt und lässt sich nicht ändern.')], 422);
        }

        $geheimnis = $zweiteStufe->geheimnisErzeugen();
        $mailcode = null;

        if ($nutzer->email) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            try {
                $nutzer->notify(new ZweiteStufeCode($code));
            } catch (Throwable $fehler) {
                report($fehler);

                return response()->json([
                    'message' => __('Die Mail mit dem Bestätigungscode ließ sich nicht senden. Bitte wenden Sie sich an Ihre Administration.'),
                ], 422);
            }

            $mailcode = $this->mailcodeHash($code);
        }

        return response()->json([
            // Zum Abtippen und fuer den QR-Code, den die App daraus malt.
            'geheimnis' => $geheimnis,
            'adresse' => $zweiteStufe->adresse($nutzer, $geheimnis),
            'braucht_mail_code' => $mailcode !== null,
            'vorgang' => Crypt::encryptString(json_encode([
                // Zweck: Der Schluessel verschluesselt auch anderes; ein
                // Geheimtext von anderswo darf hier nicht durchgehen.
                'zweck' => 'mobil-zweite-stufe',
                'id' => $nutzer->id,
                'geheimnis' => $geheimnis,
                'mailcode' => $mailcode,
                'bis' => now()->addMinutes(self::EINRICHTUNG_MINUTEN)->timestamp,
            ])),
        ]);
    }

    /**
     * Schritt zwei: der Code aus der App - und, wo noetig, der aus der Mail.
     *
     * Die Wiederherstellungscodes stehen einmal in der Antwort und nie
     * wieder. Sie liegen verschluesselt; sie noch einmal herzuzeigen hiesse,
     * sie noch einmal preiszugeben.
     */
    public function zweiteStufeBestaetigen(Request $request, ZweiteStufe $zweiteStufe): JsonResponse
    {
        $nutzer = $request->user();

        if ($nutzer->istDemoGeschuetzt()) {
            return response()->json(['message' => __('Dieser Demo-Zugang ist gesperrt und lässt sich nicht ändern.')], 422);
        }

        $request->validate([
            'vorgang' => ['required', 'string'],
            'code' => ['required', 'string'],
            'mail_code' => ['nullable', 'string'],
        ]);

        $vorgang = $this->vorgang($request->input('vorgang'), $nutzer->id);

        if ($vorgang['mailcode'] !== null) {
            $eingabe = preg_replace('/\s+/', '', (string) $request->input('mail_code'));

            if (! hash_equals($vorgang['mailcode'], $this->mailcodeHash((string) $eingabe))) {
                throw ValidationException::withMessages([
                    'mail_code' => __('Der Code aus der Mail stimmt nicht.'),
                ]);
            }
        }

        if (! $zweiteStufe->stimmt($vorgang['geheimnis'], $request->input('code'))) {
            throw ValidationException::withMessages([
                'code' => __('Der Code stimmt nicht. Steht in der App die richtige Uhrzeit?'),
            ]);
        }

        $codes = $zweiteStufe->wiederherstellungscodes();

        $nutzer->forceFill([
            'two_factor_secret' => $vorgang['geheimnis'],
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => null,
        ])->save();

        if ($nutzer->email) {
            try {
                $nutzer->notify(new ZweiteStufeEingerichtet);
            } catch (Throwable $fehler) {
                // Eingerichtet ist eingerichtet - eine gescheiterte
                // Hinweismail darf das nicht rueckgaengig machen.
                report($fehler);
            }
        }

        return response()->json(['codes' => $codes]);
    }

    /**
     * Neue Wiederherstellungscodes. Braucht das Kennwort: Wer an einem
     * offenen Geraet sitzt, soll sich nicht acht Dauerzugaenge ausdrucken
     * koennen.
     */
    public function wiederherstellungscodes(Request $request, ZweiteStufe $zweiteStufe): JsonResponse
    {
        $nutzer = $request->user();

        if ($nutzer->istDemoGeschuetzt()) {
            return response()->json(['message' => __('Dieser Demo-Zugang ist gesperrt und lässt sich nicht ändern.')], 422);
        }

        $request->validate(['password' => ['required', 'current_password']]);

        $codes = $zweiteStufe->wiederherstellungscodes();
        $nutzer->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return response()->json(['codes' => $codes]);
    }

    /** Abschalten - ebenfalls nur mit Kennwort. */
    public function zweiteStufeAus(Request $request): JsonResponse
    {
        $nutzer = $request->user();

        if ($nutzer->istDemoGeschuetzt()) {
            return response()->json(['message' => __('Dieser Demo-Zugang ist gesperrt und lässt sich nicht ändern.')], 422);
        }

        // Verlangt der Administrator sie, laesst sie sich nicht abschalten -
        // sonst waere die Pflicht ein Knopf, den man einmal drueckt.
        if ($nutzer->two_factor_required) {
            return response()->json([
                'message' => __('Ihr Administrator verlangt die zweite Stufe. Sie lässt sich nicht abschalten.'),
            ], 422);
        }

        $request->validate(['password' => ['required', 'current_password']]);

        $nutzer->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        return response()->json(['zweite_stufe' => false]);
    }

    /**
     * Das eigene Konto loeschen.
     *
     * Braucht das Kennwort und ist nicht rueckgaengig zu machen. Die Token
     * dieses Nutzers fallen mit ihm weg (User::booted), die App ist damit
     * abgemeldet - und bleibt es.
     */
    public function loeschen(Request $request): JsonResponse
    {
        $nutzer = $request->user();

        if ($nutzer->istDemoGeschuetzt()) {
            return response()->json(['message' => __('Ein Demo-Zugang lässt sich nicht löschen.')], 422);
        }

        $request->validate(['password' => ['required', 'current_password']]);

        $nutzer->delete();

        return response()->json([
            'message' => __('Konto gelöscht.'),
            'neu_anmelden' => true,
        ]);
    }

    private function mailcodeHash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /**
     * Den begonnenen Vorgang auspacken - und pruefen, dass er zu diesem
     * Nutzer gehoert und noch gilt.
     *
     * @return array{geheimnis: string, mailcode: ?string}
     */
    private function vorgang(string $verschluesselt, int $nutzerId): array
    {
        try {
            $vorgang = json_decode(Crypt::decryptString($verschluesselt), true);
        } catch (DecryptException) {
            throw $this->einrichtungAbgelaufen();
        }

        if (! is_array($vorgang)
            || ($vorgang['zweck'] ?? null) !== 'mobil-zweite-stufe'
            || ($vorgang['id'] ?? null) !== $nutzerId
            || ! isset($vorgang['geheimnis'], $vorgang['bis'])
            || $vorgang['bis'] < now()->timestamp
        ) {
            throw $this->einrichtungAbgelaufen();
        }

        return ['geheimnis' => $vorgang['geheimnis'], 'mailcode' => $vorgang['mailcode'] ?? null];
    }

    private function einrichtungAbgelaufen(): ValidationException
    {
        return ValidationException::withMessages([
            'vorgang' => __('Die Einrichtung wurde nicht begonnen oder ist abgelaufen.'),
        ]);
    }

    /** Wie viel Changelog hoechstens hinausgeht. */
    public const CHANGELOG_ZEICHEN = 20000;

    /**
     * Das Changelog und die Version, wie sie im Browser unter der
     * Seitenleiste haengen - aber nur die juengsten Eintraege.
     *
     * Die Datei ist ueber 300 KB gross. Sie bei jedem Blick ganz auf ein
     * Telefon zu schicken, waere Verschwendung, und ein Textfeld mit
     * dreihunderttausend Zeichen zeigt gar nichts mehr. Geschnitten wird an
     * einer Ueberschrift, nicht mitten im Satz; dass etwas fehlt, sagt
     * "gekuerzt".
     *
     * Roher Markdown-Text: Ihn zu formatieren waere ein zweites Format, das
     * mit jeder Zeile im Changelog brechen kann.
     */
    public function changelog(): JsonResponse
    {
        $ganz = Changelog::roh();
        $text = $ganz;
        $gekuerzt = false;

        if (mb_strlen($ganz) > self::CHANGELOG_ZEICHEN) {
            $text = mb_substr($ganz, 0, self::CHANGELOG_ZEICHEN);
            $gekuerzt = true;

            // An der letzten vollstaendigen Ueberschrift abschneiden, damit
            // kein halber Eintrag dasteht.
            $stelle = mb_strrpos($text, "\n## ");

            if ($stelle !== false) {
                $text = mb_substr($text, 0, $stelle + 1);
            }
        }

        return response()->json([
            'version' => Changelog::version(),
            'text' => $text,
            'gekuerzt' => $gekuerzt,
        ]);
    }

    /**
     * Die alte Adresse erfaehrt von der Aenderung. Ein Mailserver, der
     * steht, darf eine schon gespeicherte Aenderung nicht rueckgaengig
     * machen - deshalb nur gemeldet.
     */
    private function alteAdresseBenachrichtigen(string $alt, string $name, string $neu): void
    {
        try {
            Notification::route('mail', $alt)->notify(new EmailChanged($name, $neu));
        } catch (Throwable $fehler) {
            report($fehler);
        }
    }
}
