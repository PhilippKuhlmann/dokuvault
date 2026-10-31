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
 * One login often serves several systems. It is documented once in the login
 * entry and only attached to the device. Permissions on the device stay with the
 * caller; which kinds of credentials a user may see is decided here.
 */
class CredentialLinking
{
    /**
     * Welche Arten von Zugangsdaten dieser Benutzer sehen darf.
     *
     * Passwords and SSH keys live in the same table but have separate
     * permissions. Without that distinction anyone with the login right would
     * see the keys through the device block as well.
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
            // Checked against the customer: otherwise one attaches another
            // customer's credentials to one's own device with a guessed id.
            'login_id' => ['required', Rule::exists('login_generals', 'id')
                ->where('customer_id', $customerId)
                // Only the kinds this user may see: otherwise they attach a
                // key with a guessed id that they would not get to see in
                // their own list at all.
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

    /** Skip duplicate links silently - the unique index would throw a 500. */
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
     * Only releases the link - the login entry itself stays.
     * Only links of kinds the user may see: whoever sees no SSH keys
     * releases none either.
     */
    public function detach(Model $device, int $linkId, User $nutzer): void
    {
        $device->credentialLinks()
            ->whereKey($linkId)
            ->whereHas('login', fn ($q) => $q->whereIn('kind', $this->visibleKinds($nutzer)))
            ->delete();
    }

    /**
     * Logins that can still be attached: visible kinds, not yet linked. The
     * the keys - an SSH key belongs on a device just as much as a password
     * does.
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
