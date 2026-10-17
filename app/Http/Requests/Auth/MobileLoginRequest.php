<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Die Anmeldung aus der App - dieselben Zaehler, dieselben Meldungen.
 *
 * Erbt von LoginRequest, damit Bremse und Zaehler dieselben sind wie im
 * Browser: Wer dort gesperrt ist, kommt nicht ueber die App herein, und
 * umgekehrt. Anders ist nur der Schluss - die App hat keine Sitzung, in die
 * Auth::attempt() jemanden anmelden koennte. Sie bekommt ein Token, und das
 * gibt der Controller erst aus, wenn auch die zweite Stufe stimmt.
 */
class MobileLoginRequest extends LoginRequest
{
    public function rules()
    {
        return parent::rules() + [
            // Steht als Name am Token - damit in der Liste der Token zu
            // erkennen ist, welches Telefon es hat.
            'geraet' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Prueft Benutzername und Kennwort, ohne jemanden anzumelden.
     *
     * @throws ValidationException
     */
    public function nutzerPruefen(): User
    {
        $this->ensureIsNotRateLimited();

        $zugang = $this->only('username', 'password');

        // validate() statt attempt(): Es meldet niemanden an und braucht keine
        // Sitzung. Dafuer feuert es kein Failed-Ereignis - das schreibt
        // AnmeldungProtokollieren ins Protokoll, also hier von Hand.
        if (! Auth::validate($zugang)) {
            event(new Failed('sanctum', Auth::getLastAttempted(), $zugang));

            $this->fehlversuch();
        }

        /** @var User $nutzer */
        $nutzer = Auth::getLastAttempted();

        // Wie im Browser erst nach dem richtigen Kennwort: Wer es kennt, weiss
        // ohnehin, dass es den Zugang gibt.
        if ($nutzer->istDeaktiviert()) {
            throw ValidationException::withMessages([
                'username' => __('Dieser Zugang ist deaktiviert. Wenden Sie sich an Ihre Administration.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->herkunftKey());

        return $nutzer;
    }
}
