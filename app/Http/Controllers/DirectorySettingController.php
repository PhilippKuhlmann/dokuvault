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

/**
 * Administration > Anmeldung: signing in through an on-premises Active
 * Directory, next to local users. Connection, a test of it, a test sign-in
 * that creates nothing, and which group grants which role.
 */
class DirectorySettingController extends Controller
{
    public function index()
    {
        return view('admin.directory.index', [
            'enabled' => (bool) Setting::wert(LdapConfig::ENABLED),
            'host' => LdapConfig::host(),
            'port' => Setting::wert(LdapConfig::PORT),
            'encryption' => LdapConfig::encryption(),
            'verifyCert' => LdapConfig::verifyCert(),
            'baseDn' => LdapConfig::baseDn(),
            'bindDn' => LdapConfig::bindDn(),
            // The password never goes out again - only whether one is set.
            'hatKennwort' => LdapConfig::bindPassword() !== null,
            'domain' => Setting::wert(LdapConfig::DOMAIN),
            'extension' => LdapConfig::extensionLoaded(),
            'zuordnungen' => DirectoryGroupRole::where('source', DirectoryLogin::SOURCE)->with('role')->orderBy('position')->orderBy('id')->get(),
            'rollen' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $daten = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'host' => ['nullable', 'string', 'max:255', 'required_if_accepted:enabled'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['ldaps', 'starttls'])],
            'verify_cert' => ['nullable', 'boolean'],
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

        // ?? null: a field left out of the request (an older form, a
        // script) is "empty", not an error.
        Setting::setzen(LdapConfig::ENABLED, $request->boolean('enabled') ? '1' : null);
        Setting::setzen(LdapConfig::HOST, ($daten['host'] ?? null) ?: null);
        Setting::setzen(LdapConfig::PORT, ($daten['port'] ?? null) ?: null);
        Setting::setzen(LdapConfig::ENCRYPTION, $daten['encryption']);
        Setting::setzen(LdapConfig::VERIFY_CERT, $request->boolean('verify_cert') ? '1' : '0');
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
        $daten = $request->validate([
            'group' => ['required', 'string', 'max:500'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->whereNull('deleted_at')],
        ], [], ['group' => __('Gruppe'), 'role_id' => __('Rolle')]);

        DirectoryGroupRole::create([
            'source' => DirectoryLogin::SOURCE,
            'group' => trim($daten['group']),
            'role_id' => $daten['role_id'],
            'position' => (int) DirectoryGroupRole::where('source', DirectoryLogin::SOURCE)->max('position') + 1,
        ]);

        return redirect(route('admin.directory.index'))->with('success', __('Gruppe zugeordnet.'));
    }

    public function destroyGroup(DirectoryGroupRole $directoryGroupRole)
    {
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
            $neu = $alle->all();
            [$neu[$index], $neu[$ziel]] = [$neu[$ziel], $neu[$index]];
            foreach (array_values($neu) as $position => $zuordnung) {
                $zuordnung->update(['position' => $position + 1]);
            }
        }

        return redirect(route('admin.directory.index'));
    }
}
