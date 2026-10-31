<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Anmeldungen ins Aktivitätsprotokoll.
 *
 * Until then it held who changed which server - but not who signed in at
 * all. In a tool that holds the passwords of entire customer networks that is
 * the first question after an incident, and it was the only one that could
 * not be answered.
 *
 * The entries expire with the same retention as the rest of the log - see
 * the admin area under "Protokoll".
 */
class LogLogin
{
    public function __construct(private Request $request) {}

    public function handleLogin(Login $ereignis): void
    {
        $nutzer = $ereignis->user;

        if ($nutzer instanceof User) {
            // saveQuietly: a normal save() would run through TracksChanges and
            // hang an additional "changed" entry on the user for every
            // sign-in - the log would be twice as long and half as useful.
            // brauchbar.
            $nutzer->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $this->request->ip(),
            ])->saveQuietly();
        }

        $this->schreiben('anmeldung', 'Angemeldet', $nutzer, $nutzer?->name);
    }

    /**
     * A failed attempt.
     *
     * The attempted username belongs in it - it is half the answer to "who
     * was that?". The attempted password does not: it is part of the event,
     * and whoever mistypes would have their real password standing in the log
     * in plain text.
     */
    public function handleFailed(Failed $ereignis): void
    {
        $benutzername = (string) ($ereignis->credentials['username'] ?? '');

        $this->schreiben(
            'anmeldung_gescheitert',
            'Anmeldung gescheitert',
            $ereignis->user instanceof User ? $ereignis->user : null,
            $benutzername !== '' ? $benutzername : null,
        );
    }

    /** The throttle has bitten - see LoginRequest. */
    public function handleLockout(Lockout $ereignis): void
    {
        $this->schreiben(
            'anmeldung_gesperrt',
            'Anmeldung gesperrt',
            null,
            (string) $ereignis->request->input('username') ?: null,
        );
    }

    /**
     * @param  string|null  $objekt  What stands in the "Objekt" column - the
     *                               name of the user, or for an unknown
     *                               account the attempted username.
     */
    private function schreiben(string $ereignis, string $beschreibung, ?User $nutzer, ?string $objekt): void
    {
        activity()
            ->event($ereignis)
            ->when($nutzer !== null, fn ($protokoll) => $protokoll->performedOn($nutzer)->causedBy($nutzer))
            ->withProperties([
                'objekt' => $objekt,
                // "attributes" is the key the activity page expands - that way
                // origin and browser stand where one looks for the details of
                // every other entry.
                'attributes' => array_filter([
                    'IP' => $this->request->ip(),
                    'Browser' => str($this->request->userAgent() ?? '')->limit(120)->toString() ?: null,
                ]),
            ])
            ->log($beschreibung);
    }
}
