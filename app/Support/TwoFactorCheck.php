<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Check the second-factor code at sign-in - in the browser and in the app.
 *
 * This used to sit privately in TwoFactorChallengeController. The app signs
 * in without a session and needs the same check with the same throttle: two
 * entrances with separate counters would be twice as many attempts.
 */
class TwoFactorCheck
{
    public function __construct(private TwoFactor $zweiteStufe) {}

    /**
     * Checks the one-time or recovery code.
     *
     * @return bool whether a recovery code was used up
     *
     * @throws ValidationException bei leerem, falschem oder gebremstem Code
     */
    public function check(User $nutzer, string $eingabe, string $ip): bool
    {
        $this->nichtGesperrt($nutzer, $ip);

        $eingabe = trim($eingabe);

        if ($eingabe === '') {
            throw ValidationException::withMessages(['code' => __('Bitte einen Code eingeben.')]);
        }

        // The one-time code first, the recovery code second: a six-digit code
        // from the app cannot be a recovery code, and the other way round just
        // the same - so the order costs nothing and saves telling the two
        // apart at the input.
        $mitCode = $this->codeAnnehmen($nutzer, $eingabe);
        $mitZettel = ! $mitCode && $nutzer->useRecoveryCode($eingabe);

        if (! $mitCode && ! $mitZettel) {
            RateLimiter::hit($this->schluessel($nutzer, $ip), Setting::loginLockoutSeconds());
            RateLimiter::hit($this->userKey($nutzer), Setting::loginLockoutSeconds());

            // A wrong second factor raises no Auth\Events\Failed - the password
            // was right, after all. Yet this is exactly the interesting case:
            // someone knows the password and still does not get in.
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
        RateLimiter::clear($this->userKey($nutzer));

        return $mitZettel;
    }

    /**
     * Accepts a code only once: its time step must be newer than the last
     * accepted one. The conditional update makes the check atomic, so two
     * requests with the same code cannot both pass.
     */
    private function codeAnnehmen(User $nutzer, string $eingabe): bool
    {
        $schritt = $this->zweiteStufe->newStep(
            $nutzer->two_factor_secret,
            $eingabe,
            $nutzer->two_factor_last_step,
        );

        if ($schritt === false) {
            return false;
        }

        return User::whereKey($nutzer->id)
            ->where(fn ($q) => $q->whereNull('two_factor_last_step')->orWhere('two_factor_last_step', '<', $schritt))
            ->update(['two_factor_last_step' => $schritt]) === 1;
    }

    private function schluessel(User $nutzer, string $ip): string
    {
        return 'zweite-stufe|'.$nutzer->id.'|'.$ip;
    }

    /** Counts across all addresses, so guessing from many IPs is slowed down too. */
    private function userKey(User $nutzer): string
    {
        return 'zweite-stufe|'.$nutzer->id;
    }

    /**
     * Without this throttle the second factor would be six digits to work
     * through at leisure - a million possibilities go quickly when nobody is
     * counting.
     *
     * The same numbers as at sign-in, and now the same source as well: they
     * stood here a second time, with a comment claiming they were the same.
     * A comment does not hold two numbers together.
     */
    private function nichtGesperrt(User $nutzer, string $ip): void
    {
        $schluessel = $this->schluessel($nutzer, $ip);

        // Per user the limit is higher, so one noisy address does not lock out
        // the real owner, but a distributed guess still runs into it.
        if (RateLimiter::tooManyAttempts($this->userKey($nutzer), Setting::loginAttempts() * 4)) {
            $schluessel = $this->userKey($nutzer);
        } elseif (! RateLimiter::tooManyAttempts($schluessel, Setting::loginAttempts())) {
            return;
        }

        $sekunden = RateLimiter::availableIn($schluessel);

        throw ValidationException::withMessages([
            'code' => trans('auth.throttle', [
                'seconds' => $sekunden,
                'minutes' => ceil($sekunden / 60),
            ]),
        ]);
    }
}
