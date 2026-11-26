<?php

namespace App\Support\Directory;

use App\Models\DirectoryGroupRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The hourly look at every directory account (directory:sync).
 *
 * Without it a technician disabled in AD kept a running session until it
 * expired, and an app token for its full 30 days - the sign-in checks the
 * directory, a session already open does not.
 *
 * Per account:
 * - still enabled and in a mapped group: role, name and mail follow AD;
 * - deleted, disabled, or in no mapped group any more: access ends now.
 *   A new random password hash ends every browser session
 *   (AuthenticateSession compares it) and, through User::booted, every app
 *   token; a new remember token ends "stay signed in". The account itself
 *   stays - with its history - and signs in again once AD lets it.
 *
 * The directory out of reach ends nothing: an outage must not lock every
 * technician out. The run stops and says so.
 */
class DirectorySync
{
    public function __construct(private readonly DirectoryClient $client) {}

    /** @return array{geprueft: int, aktualisiert: int, beendet: int} */
    public function run(): array
    {
        $ergebnis = ['geprueft' => 0, 'aktualisiert' => 0, 'beendet' => 0];

        $konten = User::where('auth_source', DirectoryLogin::SOURCE)
            ->whereNull('deactivated_at')
            ->whereNull('directory_revoked_at')
            ->whereNotNull('external_id')
            ->get();

        foreach ($konten as $nutzer) {
            // DirectoryUnavailable stops the whole run - see above.
            $konto = $this->client->find($nutzer->external_id);
            $rolle = $konto ? DirectoryGroupRole::roleFor(DirectoryLogin::SOURCE, $konto->groups) : null;
            $ergebnis['geprueft']++;

            if ($konto === null || $rolle === null) {
                $this->endAccess($nutzer, $konto === null
                    ? 'im AD gesperrt oder gelöscht'
                    : 'in keiner freigegebenen Gruppe mehr');
                $ergebnis['beendet']++;

                continue;
            }

            $nutzer->forceFill([
                'role_id' => $rolle->id,
                'name' => $konto->name ?: $nutzer->name,
            ]);
            if (filled($konto->email) && ! User::where('email', $konto->email)->whereKeyNot($nutzer->id)->exists()) {
                $nutzer->email = $konto->email;
            }

            if ($nutzer->isDirty()) {
                $nutzer->save();
                $ergebnis['aktualisiert']++;
            }
        }

        return $ergebnis;
    }

    private function endAccess(User $nutzer, string $grund): void
    {
        $nutzer->forceFill([
            'password' => Hash::make(Str::random(64)),
            'remember_token' => Str::random(60),
            'directory_revoked_at' => now(),
        ])->save();

        // Every bearer credential ends too: the password change above only
        // revokes the app tokens, an integration token would stay valid.
        $nutzer->tokens()->delete();

        activity()
            ->event('ad_abgleich')
            ->performedOn($nutzer)
            ->withProperties(['objekt' => $nutzer->name])
            ->log('Zugang beendet: '.$grund);
    }
}
