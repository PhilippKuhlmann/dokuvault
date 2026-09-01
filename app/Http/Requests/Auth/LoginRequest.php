<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Wie lange eine Sperre haelt. Laravel liefert 60 Sekunden mit - damit sind
     * 5 Versuche pro Minute erlaubt, also 300 pro Stunde und Konto. Fuer ein
     * Werkzeug, in dem die Kennwoerter ganzer Kundennetze liegen, ist das zu
     * grosszuegig; eine Viertelstunde macht aus 300 Versuchen 20.
     */
    private const SPERRE = 900;

    /** Fehlversuche je Konto und Herkunft, bevor gesperrt wird. */
    private const VERSUCHE_JE_KONTO = 5;

    /**
     * Fehlversuche je Herkunft ueber alle Konten hinweg. Der Zaehler oben
     * enthaelt den Nutzernamen im Schluessel - wer ein einziges Kennwort gegen
     * viele Nutzernamen probiert ("Kennwort-Spraying"), loest ihn nie aus, weil
     * jeder Name seinen eigenen frischen Zaehler bekommt. Dieser zweite Zaehler
     * schon. Die Zahl ist bewusst hoch: ganze Bueros haengen hinter einer IP.
     */
    private const VERSUCHE_JE_HERKUNFT = 30;

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

        if (! Auth::attempt($this->only('username', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::SPERRE);
            RateLimiter::hit($this->herkunftKey(), self::SPERRE);

            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        // Auch den Herkunftszaehler leeren: sonst waere ein Buero, in dem sich
        // morgens 30 Leute vertippen, fuer alle gesperrt, ohne Weg zurueck
        // ausser Warten. Wer sich richtig anmeldet, hat bewiesen, dass er
        // hierher gehoert.
        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->herkunftKey());
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
        $gesperrt = RateLimiter::tooManyAttempts($this->throttleKey(), self::VERSUCHE_JE_KONTO)
            || RateLimiter::tooManyAttempts($this->herkunftKey(), self::VERSUCHE_JE_HERKUNFT);

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
