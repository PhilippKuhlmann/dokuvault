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
 * Bis dahin stand dort, wer welchen Server geändert hat - aber nicht, wer sich
 * überhaupt angemeldet hat. In einem Werkzeug, das die Kennwörter ganzer
 * Kundennetze hält, ist das die erste Frage nach einem Vorfall und war die
 * einzige, die sich nicht beantworten liess.
 *
 * Die Einträge laufen mit derselben Aufbewahrungsfrist ab wie der Rest des
 * Protokolls - siehe den Adminbereich unter "Protokoll".
 */
class AnmeldungProtokollieren
{
    public function __construct(private Request $request) {}

    public function handleLogin(Login $ereignis): void
    {
        $nutzer = $ereignis->user;

        if ($nutzer instanceof User) {
            // saveQuietly: Ein normales save() liefe durch TracksChanges und
            // haenge an jede Anmeldung zusaetzlich einen "Geaendert"-Eintrag
            // am Benutzer - das Protokoll waere doppelt so lang und halb so
            // brauchbar.
            $nutzer->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $this->request->ip(),
            ])->saveQuietly();
        }

        $this->schreiben('anmeldung', 'Angemeldet', $nutzer, $nutzer?->name);
    }

    /**
     * Ein gescheiterter Versuch.
     *
     * Der versuchte Benutzername gehoert hinein - er ist die halbe Antwort auf
     * "wer war das?". Das versuchte Kennwort nicht: Es steht im Ereignis mit
     * drin, und wer sich vertippt, haette sein richtiges Kennwort im Klartext
     * im Protokoll stehen.
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

    /** Die Bremse hat zugeschlagen - siehe LoginRequest. */
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
     * @param  string|null  $objekt  Was in der Spalte "Objekt" steht - der Name
     *                               des Benutzers, oder bei einem unbekannten
     *                               Zugang der versuchte Benutzername.
     */
    private function schreiben(string $ereignis, string $beschreibung, ?User $nutzer, ?string $objekt): void
    {
        activity()
            ->event($ereignis)
            ->when($nutzer !== null, fn ($protokoll) => $protokoll->performedOn($nutzer)->causedBy($nutzer))
            ->withProperties([
                'objekt' => $objekt,
                // "attributes" ist der Schluessel, den die Protokollseite
                // aufklappt - so stehen Herkunft und Browser dort, wo man bei
                // jedem anderen Eintrag die Einzelheiten sucht.
                'attributes' => array_filter([
                    'IP' => $this->request->ip(),
                    'Browser' => str($this->request->userAgent() ?? '')->limit(120)->toString() ?: null,
                ]),
            ])
            ->log($beschreibung);
    }
}
