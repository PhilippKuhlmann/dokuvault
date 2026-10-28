<?php

namespace App\Support;

use App\Models\CredentialLink;
use App\Models\LoginGeneral;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Linking credentials to a device - shared by the block in the browser
 * (App\Livewire\DeviceCredentials) and the iOS app.
 *
 * Ein Login gilt oft fuer mehrere Systeme. Es steht einmal im Login-Eintrag
 * und wird am Geraet nur angehaengt. Permissions on the device stay with the
 * caller; which kinds of credentials a user may see is decided here.
 */
class CredentialLinking
{
    /**
     * Welche Arten von Zugangsdaten dieser Benutzer sehen darf.
     *
     * Kennwoerter und SSH-Schluessel liegen in derselben Tabelle, haben aber
     * getrennte Rechte. Ohne diese Unterscheidung saehe jeder mit Login-Recht
     * ueber den Geraeteblock auch die Schluessel.
     *
     * @return array<int, string>
     */
    public function visibleKinds(User $nutzer): array
    {
        return collect([
            LoginGeneral::KIND => 'logingeneral_viewAny',
            SshKey::KIND => 'sshkey_viewAny',
        ])->filter(fn ($recht) => Gate::forUser($nutzer)->allows($recht))->keys()->all();
    }

    /** Attaching an existing login. */
    public function attachRules(int $customerId, User $nutzer): array
    {
        return [
            // Kundengebunden geprüft: sonst hängt man sich mit einer geratenen ID
            // fremde Zugangsdaten an das eigene Gerät.
            'login_id' => ['required', Rule::exists('login_generals', 'id')
                ->where('customer_id', $customerId)
                // Nur die Arten, die dieser Benutzer sehen darf: sonst haengt
                // er mit einer geratenen Id einen Schluessel an, den er in
                // seiner eigenen Liste gar nicht zu sehen bekaeme.
                ->whereIn('kind', $this->visibleKinds($nutzer))
                ->whereNull('deleted_at')],
            'note' => ['nullable', 'max:255'],
        ];
    }

    /** Creating a new login and attaching it at once. */
    public function createRules(): array
    {
        return [
            'name' => ['required', 'max:255'],
            'username' => ['nullable', 'max:255'],
            'password' => ['nullable', 'max:255'],
            'note' => ['nullable', 'max:255'],
        ];
    }

    public function attributeNames(): array
    {
        return [
            'login_id' => __('Zugangsdaten'),
            'name' => __('Bezeichnung'),
            'username' => __('Benutzername'),
            'password' => __('Kennwort'),
            'note' => __('Notiz'),
        ];
    }

    /** Doppelte Verknüpfungen still übergehen - der Unique-Index würde sonst 500 werfen. */
    public function attach(Model $device, int $loginId, ?string $note): CredentialLink
    {
        return $device->credentialLinks()->firstOrCreate(
            ['login_general_id' => $loginId],
            ['customer_id' => $device->customer_id, 'note' => $note ?: null],
        );
    }

    public function create(Model $device, array $daten): CredentialLink
    {
        $login = LoginGeneral::create([
            'customer_id' => $device->customer_id,
            'name' => $daten['name'],
            'username' => ($daten['username'] ?? null) ?: null,
            'password' => ($daten['password'] ?? null) ?: null,
        ]);

        return $this->attach($device, $login->id, $daten['note'] ?? null);
    }

    /**
     * Löst nur die Verknüpfung - der Login-Eintrag selbst bleibt bestehen.
     * Nur Verknüpfungen von Arten, die der Nutzer sehen darf: Wer keine
     * SSH-Schlüssel sieht, löst auch keine.
     */
    public function detach(Model $device, int $linkId, User $nutzer): void
    {
        $device->credentialLinks()
            ->whereKey($linkId)
            ->whereHas('login', fn ($q) => $q->whereIn('kind', $this->visibleKinds($nutzer)))
            ->delete();
    }

    /**
     * Logins that can still be attached: visible kinds, not yet linked. Auch
     * die Schluessel - an ein Geraet gehoert genauso gut ein SSH-Schluessel
     * wie ein Kennwort.
     */
    public function available(Model $device, User $nutzer): Collection
    {
        return LoginGeneral::withoutGlobalScope(LoginGeneral::SCOPE)
            ->where('customer_id', $device->customer_id)
            ->whereIn('kind', $this->visibleKinds($nutzer))
            ->whereNotIn('id', $device->credentialLinks()->pluck('login_general_id'))
            ->orderBy('name')
            ->get();
    }
}
