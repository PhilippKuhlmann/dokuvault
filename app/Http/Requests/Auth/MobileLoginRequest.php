<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Signing in from the app - the same counters, the same messages.
 *
 * Inherits from LoginRequest so that throttle and counters are the same as
 * in the browser: whoever is locked out there does not get in through the
 * app, and the other way round. Only the ending differs - the app has no
 * session for Auth::attempt() to sign anyone into. It gets a token, and the
 * controller hands that out only once the second factor is right as well.
 */
class MobileLoginRequest extends LoginRequest
{
    public function rules()
    {
        return parent::rules() + [
            // Stands as the name on the token - so the token list shows which
            // phone holds it.
            'geraet' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Checks username and password without signing anyone in.
     *
     * @throws ValidationException
     */
    public function checkUser(): User
    {
        $this->ensureIsNotRateLimited();

        $zugang = $this->only('username', 'password');

        // validate() instead of attempt(): it signs nobody in and needs no
        // session. In exchange it fires no Failed event - that is written by
        // AnmeldungProtokollieren ins Protokoll, also hier von Hand.
        if (! Auth::validate($zugang)) {
            event(new Failed('sanctum', Auth::getLastAttempted(), $zugang));

            $this->failedAttempt();
        }

        /** @var User $nutzer */
        $nutzer = Auth::getLastAttempted();

        // Wie im Browser erst nach dem richtigen Kennwort: Wer es kennt, weiss
        // says anyway that the account exists.
        if ($nutzer->isDisabled()) {
            throw ValidationException::withMessages([
                'username' => __('Dieser Zugang ist deaktiviert. Wenden Sie sich an Ihre Administration.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->herkunftKey());

        return $nutzer;
    }
}
