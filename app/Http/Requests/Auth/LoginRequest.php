<?php

namespace App\Http\Requests\Auth;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Die Zahlen stehen unter Einstellungen > Sicherheit
    |--------------------------------------------------------------------------
    |
    | Zwei Zaehler. Der erste haengt am Nutzernamen. Wer ein einziges Kennwort
    | gegen viele Nutzernamen probiert ("Kennwort-Spraying"), loest ihn nie
    | aus, weil jeder Name seinen eigenen frischen Zaehler bekommt - deshalb
    | der zweite, der nur die Herkunft kennt.
    |
    | Dieselben Zahlen gelten in der zweiten Stufe. Sie standen dort ein
    | zweites Mal im Code, mit dem Kommentar, es seien dieselben.
    |
    */

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @return void
     *
     * @throws ValidationException
     */
    public function authenticate()
    {
        $this->ensureIsNotRateLimited();

        // Die Sperre wird VOR der Anmeldung geprueft, nicht danach.
        //
        // Vorher stand sie hinter Auth::attempt(), und das war falsch:
        // attempt() meldet bei richtigem Kennwort an und feuert dabei
        // Illuminate\Auth\Events\Login. Daran haengt AnmeldungProtokollieren,
        // das last_login_at setzt und "Angemeldet" ins Protokoll schreibt. Das
        // nachgeschobene logout() nimmt die Sitzung zurueck, den Eintrag aber
        // nicht: Jeder abgewiesene Versuch eines Gesperrten stand als
        // erfolgreiche Anmeldung im Protokoll, mit frischem Datum in der
        // Spalte "Zuletzt angemeldet". Ausgerechnet dieses Protokoll ist der
        // Grund, einen Zugang zu sperren statt ihn zu loeschen.
        //
        // Auth::validate() prueft dieselben Zugangsdaten, meldet aber
        // niemanden an und feuert kein Login-Ereignis. Der zweite
        // Hash-Durchlauf faellt nur bei gesperrten Zugaengen an - der
        // Normalfall kommt weiterhin mit einem aus.
        $nutzer = User::where('username', $this->input('username'))->first();

        if ($nutzer?->istDeaktiviert()) {
            // Erst bei stimmendem Kennwort den Grund nennen: Wer es kennt,
            // weiss ohnehin, dass es den Zugang gibt - "Zugangsdaten falsch"
            // schickte ihn nur los, ein Kennwort zu suchen, das er gar nicht
            // verloren hat. Wer es nicht kennt, erfaehrt hier nichts.
            if (Auth::validate($this->only('username', 'password'))) {
                throw ValidationException::withMessages([
                    'username' => __('Dieser Zugang ist deaktiviert. Wenden Sie sich an Ihre Administration.'),
                ]);
            }

            // Falsches Kennwort an einem gesperrten Zugang: genau wie sonst.
            $this->fehlversuch();
        }

        if (! Auth::attempt($this->only('username', 'password'), $this->boolean('remember'))) {
            $this->fehlversuch();
        }

        // Auch den Herkunftszaehler leeren: sonst waere ein Buero, in dem sich
        // morgens 30 Leute vertippen, fuer alle gesperrt, ohne Weg zurueck
        // ausser Warten. Wer sich richtig anmeldet, hat bewiesen, dass er
        // hierher gehoert.
        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->herkunftKey());
    }

    /**
     * Ein gescheiterter Versuch: Zaehler hoch, immer dieselbe Meldung.
     *
     * An zwei Stellen gebraucht - beim falschen Kennwort und beim falschen
     * Kennwort an einem gesperrten Zugang. Beide muessen sich gleich
     * verhalten, sonst verraet schon die Meldung, welcher Fall vorliegt.
     *
     * @throws ValidationException
     */
    private function fehlversuch(): void
    {
        $sperre = Setting::anmeldungSperreSekunden();

        RateLimiter::hit($this->throttleKey(), $sperre);
        RateLimiter::hit($this->herkunftKey(), $sperre);

        throw ValidationException::withMessages([
            'username' => trans('auth.failed'),
        ]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @return void
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited()
    {
        $gesperrt = RateLimiter::tooManyAttempts($this->throttleKey(), Setting::anmeldungVersuche())
            || RateLimiter::tooManyAttempts($this->herkunftKey(), Setting::anmeldungHerkunft());

        if (! $gesperrt) {
            return;
        }

        event(new Lockout($this));

        $seconds = max(
            RateLimiter::availableIn($this->throttleKey()),
            RateLimiter::availableIn($this->herkunftKey()),
        );

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * @return string
     */
    public function throttleKey()
    {
        return Str::transliterate(Str::lower($this->input('username')).'|'.$this->ip());
    }

    /**
     * Schluessel des Zaehlers, der nur die Herkunft kennt - siehe
     * VERSUCHE_JE_HERKUNFT.
     */
    public function herkunftKey(): string
    {
        return 'anmeldung|'.$this->ip();
    }
}
