<?php

namespace App\Http\Controllers;

use App\Models\DirectoryGroupRole;
use App\Models\Role;
use App\Models\Setting;
use App\Support\Directory\DirectoryClient;
use App\Support\Directory\DirectoryLogin;
use App\Support\Directory\DirectoryUnavailable;
use App\Support\Directory\LdapConfig;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Administration > Anmeldung: signing in through an on-premises Active
 * Directory, next to local users. Connection, a test of it, a test sign-in
 * that creates nothing, and which group grants which role.
 */
class DirectorySettingController extends Controller
{
    public function index(DirectoryClient $verzeichnis)
    {
        // The groups to pick from, once the connection is entered. Out of
        // reach: the text field stays, and the page says why.
        $gruppen = null;
        $gruppenFehler = null;
        if (LdapConfig::configured()) {
            try {
                $gruppen = $verzeichnis->groups();
            } catch (DirectoryUnavailable $fehler) {
                $gruppenFehler = $fehler->getMessage();
            }
        }

        return view('admin.directory.index', [
            'gruppen' => $gruppen,
            'gruppenFehler' => $gruppenFehler,
            // DN -> name, to show a stored DN by its name in the list.
            'gruppenNamen' => collect($gruppen ?? [])->mapWithKeys(fn ($g) => [mb_strtolower($g['dn']) => $g['name']])->all(),
            'enabled' => (bool) Setting::wert(LdapConfig::ENABLED),
            'host' => LdapConfig::host(),
            'port' => Setting::wert(LdapConfig::PORT),
            'encryption' => LdapConfig::encryption(),
            'unverschluesselt' => LdapConfig::unencrypted(),
            'verifyCert' => LdapConfig::verifyCert(),
            'requireTwoFactor' => LdapConfig::requireTwoFactor(),
            'baseDn' => LdapConfig::baseDn(),
            'bindDn' => LdapConfig::bindDn(),
            // The password never goes out again - only whether one is set.
            'hatKennwort' => LdapConfig::bindPassword() !== null,
            'domain' => Setting::wert(LdapConfig::DOMAIN),
            'extension' => LdapConfig::extensionLoaded(),
            // Rows whose role the viewer may not hand out are shown but locked.
            'zuordnungen' => DirectoryGroupRole::where('source', DirectoryLogin::SOURCE)->with('role.permissions')->orderBy('position')->orderBy('id')->get()
                ->each(fn (DirectoryGroupRole $mapping) => $mapping->setAttribute('changeable', $this->mayChange($mapping))),
            // A mapping is a role grant: only roles the viewer may assign.
            'rollen' => Role::with('permissions')->orderBy('name')->get()->filter(fn (Role $role) => auth()->user()->mayAssign($role))->values(),
        ]);
    }

    public function update(Request $request)
    {
        $daten = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'host' => ['nullable', 'string', 'max:255', 'required_if_accepted:enabled'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['ldaps', 'starttls', 'none'])],
            'verify_cert' => ['nullable', 'boolean'],
            'require_2fa' => ['nullable', 'boolean'],
            'base_dn' => ['nullable', 'string', 'max:500', 'required_if_accepted:enabled'],
            'bind_dn' => ['nullable', 'string', 'max:500', 'required_if_accepted:enabled'],
            'bind_password' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:15', 'regex:/^[A-Za-z0-9._-]*$/'],
        ], [], [
            'host' => __('Server'),
            'port' => __('Port'),
            'base_dn' => __('Basis-DN'),
            'bind_dn' => __('Dienstkonto'),
            'bind_password' => __('Kennwort'),
            'domain' => __('Domäne (NetBIOS)'),
        ]);

        // The stored service password belongs to the server it was entered
        // for: another server, port or a weaker connection - type it again,
        // or it would be sent wherever the new values point.
        $serverChanged = mb_strtolower(trim((string) ($daten['host'] ?? ''))) !== mb_strtolower(trim((string) LdapConfig::host()))
            || (int) ($daten['port'] ?? 0) !== (int) Setting::wert(LdapConfig::PORT)
            || $daten['encryption'] !== LdapConfig::encryption()
            || $request->boolean('verify_cert') !== LdapConfig::verifyCert();
        if ($serverChanged && ($daten['bind_password'] ?? '') === '' && LdapConfig::bindPassword() !== null) {
            throw ValidationException::withMessages(['bind_password' => __('Bei geändertem Server das Kennwort bitte neu eingeben.')]);
        }

        // Who switched the encryption off - and back on - belongs in the
        // log: from then on passwords cross the network readable.
        $vorher = LdapConfig::unencrypted();
        $nachher = $daten['encryption'] === 'none';
        if ($vorher !== $nachher) {
            activity()
                ->event('einstellung')
                ->causedBy(auth()->user())
                ->log($nachher
                    ? 'AD-Anmeldung ohne Verschlüsselung eingestellt'
                    : 'AD-Anmeldung wieder verschlüsselt');
        }

        // ?? null: a field left out of the request (an older form, a
        // script) is "empty", not an error.
        Setting::setzen(LdapConfig::ENABLED, $request->boolean('enabled') ? '1' : null);
        Setting::setzen(LdapConfig::HOST, ($daten['host'] ?? null) ?: null);
        Setting::setzen(LdapConfig::PORT, ($daten['port'] ?? null) ?: null);
        Setting::setzen(LdapConfig::ENCRYPTION, $daten['encryption']);
        Setting::setzen(LdapConfig::VERIFY_CERT, $request->boolean('verify_cert') ? '1' : '0');

        // The second factor for new directory accounts: a security decision,
        // so it is logged like the encryption.
        $zweiteStufe = $request->boolean('require_2fa');
        if ($zweiteStufe !== LdapConfig::requireTwoFactor()) {
            activity()
                ->event('einstellung')
                ->causedBy(auth()->user())
                ->log($zweiteStufe
                    ? 'Neue AD-Benutzer müssen die zweite Stufe einrichten'
                    : 'Neue AD-Benutzer müssen die zweite Stufe nicht mehr einrichten');
        }
        Setting::setzen(LdapConfig::REQUIRE_2FA, $zweiteStufe ? '1' : '0');
        Setting::setzen(LdapConfig::BASE_DN, ($daten['base_dn'] ?? null) ?: null);
        Setting::setzen(LdapConfig::BIND_DN, ($daten['bind_dn'] ?? null) ?: null);
        Setting::setzen(LdapConfig::DOMAIN, ($daten['domain'] ?? null) ?: null);

        // Empty means "unchanged", as with the mail password - deleting has
        // its own button.
        if (($daten['bind_password'] ?? '') !== '') {
            LdapConfig::setBindPassword($daten['bind_password']);
        }

        return redirect(route('admin.directory.index'))->with('success', __('Einstellungen gespeichert.'));
    }

    public function deletePassword()
    {
        LdapConfig::setBindPassword(null);

        return redirect(route('admin.directory.index'))->with('success', __('Kennwort entfernt.'));
    }

    /** Connects with the service account - before anyone depends on it. */
    public function test(DirectoryClient $verzeichnis)
    {
        try {
            $meldung = $verzeichnis->test();
        } catch (DirectoryUnavailable $fehler) {
            return redirect(route('admin.directory.index'))
                ->withErrors(['verbindung' => __('Keine Verbindung: :grund', ['grund' => $fehler->getMessage()])]);
        }

        return redirect(route('admin.directory.index'))->with('success', $meldung);
    }

    /**
     * Tries a sign-in without signing anyone in or creating an account:
     * which groups the directory reports, and which role that would give.
     */
    public function testUser(Request $request, DirectoryClient $verzeichnis)
    {
        $daten = $request->validate([
            'test_username' => ['required', 'string', 'max:255'],
            'test_password' => ['required', 'string'],
        ], [], ['test_username' => __('Benutzername'), 'test_password' => __('Kennwort')]);

        try {
            $konto = $verzeichnis->authenticate($daten['test_username'], $daten['test_password']);
        } catch (DirectoryUnavailable $fehler) {
            return redirect(route('admin.directory.index'))
                ->withErrors(['test_username' => __('Keine Verbindung: :grund', ['grund' => $fehler->getMessage()])]);
        }

        if ($konto === null) {
            return redirect(route('admin.directory.index'))->withInput($request->only('test_username'))
                ->withErrors(['test_username' => __('Unbekannt, Kennwort falsch oder im AD deaktiviert.')]);
        }

        $rolle = DirectoryGroupRole::roleFor(DirectoryLogin::SOURCE, $konto->groups);

        return redirect(route('admin.directory.index'))->withInput($request->only('test_username'))->with('ad_test', [
            'name' => $konto->name ?: $konto->username,
            'username' => LdapConfig::domain().'\\'.$konto->username,
            'email' => $konto->email,
            'gruppen' => collect($konto->groups)->map(fn ($g) => $g['name'] ?? $g['cn'] ?? $g['dn'])->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            'rolle' => $rolle?->name,
        ]);
    }

    public function storeGroup(Request $request)
    {
        // Picked from the list (its DN) or typed by hand - the typed one
        // wins, it is the deliberate exception.
        $data = $request->validate([
            'group' => ['nullable', 'string', 'max:500', 'required_without:group_manual'],
            'group_manual' => ['nullable', 'string', 'max:500'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereNull('deleted_at')],
        ], [], ['group' => __('Gruppe'), 'group_manual' => __('Gruppe'), 'role_id' => __('Rolle')]);

        // Everyone in the group gets this role on sign-in - the same ceiling
        // as assigning it in the user admin, or admin_setting alone would
        // be enough to map one's own group to role 1.
        $role = Role::with('permissions')->findOrFail($data['role_id']);
        abort_unless($request->user()->mayAssign($role), 403);

        DirectoryGroupRole::create([
            'source' => DirectoryLogin::SOURCE,
            'group' => trim(($data['group_manual'] ?? null) ?: $data['group']),
            'role_id' => $role->id,
            'position' => (int) DirectoryGroupRole::where('source', DirectoryLogin::SOURCE)->max('position') + 1,
        ]);

        return redirect(route('admin.directory.index'))->with('success', __('Gruppe zugeordnet.'));
    }

    public function destroyGroup(DirectoryGroupRole $directoryGroupRole)
    {
        abort_unless($this->mayChange($directoryGroupRole), 403);

        $directoryGroupRole->delete();

        return redirect(route('admin.directory.index'))->with('success', __('Zuordnung entfernt.'));
    }

    /** One place up or down - the order decides which role wins. */
    public function moveGroup(DirectoryGroupRole $directoryGroupRole, string $richtung)
    {
        abort_unless(in_array($richtung, ['up', 'down'], true), 404);

        $alle = DirectoryGroupRole::where('source', $directoryGroupRole->source)->orderBy('position')->orderBy('id')->get()->values();
        $index = $alle->search(fn ($z) => $z->id === $directoryGroupRole->id);
        $ziel = $richtung === 'up' ? $index - 1 : $index + 1;

        if ($ziel >= 0 && $ziel < $alle->count()) {
            // The order decides which role wins, so both rows that swap
            // places must carry roles the caller may assign.
            abort_unless($this->mayChange($directoryGroupRole) && $this->mayChange($alle[$ziel]), 403);

            $neu = $alle->all();
            [$neu[$index], $neu[$ziel]] = [$neu[$ziel], $neu[$index]];
            foreach (array_values($neu) as $position => $zuordnung) {
                $zuordnung->update(['position' => $position + 1]);
            }
        }

        return redirect(route('admin.directory.index'));
    }

    /** Within the caller's ceiling: the role of this mapping is one they may assign. */
    private function mayChange(DirectoryGroupRole $mapping): bool
    {
        $role = $mapping->role;
        $role?->loadMissing('permissions');

        // A mapping to a deleted role grants nothing - anyone may clear it.
        return $role === null || auth()->user()->mayAssign($role);
    }
}
