<?php

namespace App\Http\Controllers\API\Agent;

use App\Models\ADDomain;
use App\Models\ADDomainHost;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\Domain;
use App\Models\Server;
use App\Models\VM;
use Illuminate\Http\Request;

/**
 * A domain controller reports the Active Directory: the domain, its users,
 * its groups and who is a member of what. Customer-wide - AD has no site.
 */
class ActiveDirectoryController extends AgentEndpoint
{
    /**
     * Nimmt die von einem Windows-Domaincontroller gemeldeten AD-Benutzer und
     * -Gruppen entgegen (Upsert über agent_identifier = AD ObjectGUID). Das
     * Script filtert bereits am DC: nur "echte" Benutzer (inkl. eingebautem
     * Administrator, ohne Gast/krbtgt/DefaultAccount) und nur selbst
     * angelegte Gruppen (keine Built-in-Gruppen). Passwörter werden nie
     * gesetzt – die verschlüsselte Spalte bleibt allein manuell gepflegt.
     */
    public function windowsAd(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');

        $data = $request->validate([
            'domain' => ['nullable', 'string', 'max:255'],
            'users' => ['nullable', 'array'],
            'users.*.identifier' => ['required_with:users', 'string', 'max:255'],
            'users.*.firstName' => ['nullable', 'string', 'max:255'],
            'users.*.lastName' => ['nullable', 'string', 'max:255'],
            'users.*.username' => ['nullable', 'string', 'max:255'],
            'users.*.email' => ['nullable', 'string', 'max:255'],
            'users.*.enabled' => ['nullable', 'boolean'],
            'groups' => ['nullable', 'array'],
            'groups.*.identifier' => ['required_with:groups', 'string', 'max:255'],
            'groups.*.name' => ['nullable', 'string', 'max:255'],
            'groups.*.description' => ['nullable', 'string', 'max:255'],
            // Since 26.10.03: user GUIDs of the direct members. Missing (older
            // script) leaves the memberships as they are.
            'groups.*.members' => ['nullable', 'array'],
            'groups.*.members.*' => ['string', 'max:255'],
            // The domain itself (since 26.10.02). All optional: an older
            // script sends only the name, and every query on the DC may fail
            // for lack of rights or a missing module.
            'ad' => ['nullable', 'array'],
            'ad.netbios' => ['nullable', 'string', 'max:255'],
            'ad.domain_mode' => ['nullable', 'string', 'max:255'],
            'ad.upn_suffixes' => ['nullable', 'array'],
            'ad.upn_suffixes.*' => ['string', 'max:255'],
            'ad.fsmo' => ['nullable', 'array'],
            'ad.fsmo.*' => ['nullable', 'string', 'max:255'],
            'ad.domain_controllers' => ['nullable', 'array'],
            'ad.domain_controllers.*.name' => ['required', 'string', 'max:255'],
            'ad.domain_controllers.*.ip' => ['nullable', 'string', 'max:255'],
            'ad.dns_forwarders' => ['nullable', 'array'],
            'ad.dns_forwarders.*' => ['string', 'max:255'],
            'ad.dhcp_servers' => ['nullable', 'array'],
            'ad.dhcp_servers.*' => ['string', 'max:255'],
            'ad.ca_hosts' => ['nullable', 'array'],
            'ad.ca_hosts.*' => ['string', 'max:255'],
            'ad.entra_connect_host' => ['nullable', 'string', 'max:255'],
        ]);

        $domainResult = filled($data['domain'] ?? null)
            ? $this->adDomainDokumentieren($customer, $data['domain'], $data['ad'] ?? [])
            : ['unmatched_hosts' => []];

        $userCount = 0;
        foreach ($data['users'] ?? [] as $u) {
            $user = $this->findAdEntry(ADUser::class, $customer->id, $u['identifier'], 'username', $u['username'] ?? null);
            // Only what AD reported: an empty field must not wipe a value
            // entered by hand. 'password' is never touched (kept by hand).
            $user->fill(array_filter([
                'agent_identifier' => $u['identifier'],
                'firstName' => $u['firstName'] ?? null,
                'lastName' => $u['lastName'] ?? null,
                'username' => $u['username'] ?? null,
                'email' => $u['email'] ?? null,
            ], fn ($wert) => filled($wert)));
            if (array_key_exists('enabled', $u) && $u['enabled'] !== null) {
                $user->enabled = (bool) $u['enabled'];
            }
            $user->save();
            $userCount++;
        }

        $groupCount = 0;
        foreach ($data['groups'] ?? [] as $g) {
            $group = $this->findAdEntry(ADGroup::class, $customer->id, $g['identifier'], 'name', $g['name'] ?? null);
            $group->fill(array_filter([
                'agent_identifier' => $g['identifier'],
                'name' => $g['name'] ?? null,
                'description' => $g['description'] ?? null,
            ], fn ($wert) => filled($wert)))->save();
            $groupCount++;

            if (array_key_exists('members', $g) && is_array($g['members'])) {
                $this->adMitgliederAbgleichen($group, $customer->id, $g['members']);
            }
        }

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'domain' => $data['domain'] ?? null,
            'users_documented' => $userCount,
            'groups_documented' => $groupCount,
            // Machines the DC named but the documentation does not know -
            // the script prints them, so they can be documented and linked.
            'unmatched_hosts' => $domainResult['unmatched_hosts'],
        ]);
    }

    /**
     * Create or update the AD domain from what the DC reports.
     *
     * Found by customer and DNS name - a domain has no GUID worth keeping,
     * and two domains of one name at one customer do not exist. Only what
     * was reported is written: an empty answer (query failed, no rights)
     * must not wipe a value entered by hand. Notes and DSRM password are
     * never touched - the DC does not know them.
     *
     * @return array{unmatched_hosts: array<int, string>}
     */
    private function adDomainDokumentieren($customer, string $name, array $ad): array
    {
        $domaene = ADDomain::where('customer_id', $customer->id)
            ->whereRaw('LOWER(domain) = ?', [strtolower($name)])
            ->first()
            ?? new ADDomain(['customer_id' => $customer->id, 'domain' => $name, 'dsrmpassword' => '']);

        $liste = fn ($werte) => collect($werte ?? [])->map(fn ($w) => trim($w))->filter()->unique()->implode(', ') ?: null;

        $felder = [
            'netbios' => $ad['netbios'] ?? null,
            'functional_level' => $this->adFunktionsebene($ad['domain_mode'] ?? null),
            'upn_suffixes' => $liste($ad['upn_suffixes'] ?? null),
            'fsmo_holder' => $this->adFsmo($ad['fsmo'] ?? []),
            'dns_forwarders' => $liste($ad['dns_forwarders'] ?? null),
            'dhcp_server' => $liste($ad['dhcp_servers'] ?? null),
            // Found an MSOL_ account: Entra Connect runs. Its absence proves
            // nothing (Cloud Sync works without one) - so never "no".
            'entra_connect' => filled($ad['entra_connect_host'] ?? null) ? true : null,
        ];

        foreach ($felder as $feld => $wert) {
            if ($wert !== null) {
                $domaene->{$feld} = $wert;
            }
        }

        // NOT NULL without default; a new domain from an old script that
        // sends only the name gets the first label in capitals.
        $domaene->netbios ??= strtoupper(strtok($name, '.'));
        $domaene->save();

        $nichtGefunden = [];
        $rollen = [
            'dc' => collect($ad['domain_controllers'] ?? [])->pluck('name')->all(),
            'entra_connect' => array_filter([$ad['entra_connect_host'] ?? null]),
            'ca' => $ad['ca_hosts'] ?? [],
        ];

        foreach ($rollen as $rolle => $namen) {
            if (empty($namen)) {
                continue;
            }

            $gefunden = [];
            foreach ($namen as $hostname) {
                $maschine = $this->machineForHostname($customer->id, $hostname);
                $maschine ? $gefunden[] = ADDomainHost::key($maschine) : $nichtGefunden[] = $hostname;
            }

            // Only when at least one machine matched: otherwise a DC that is
            // not documented yet would remove the links set by hand.
            if ($gefunden) {
                $domaene->syncHosts($rolle, $gefunden);
            }
        }

        return ['unmatched_hosts' => array_values(array_unique($nichtGefunden))];
    }

    /**
     * Memberships of one group as AD reports them.
     *
     * Only users AD knows (with a GUID) are added or removed. A link to a
     * user documented by hand only - no GUID, so AD cannot report it - is
     * kept: the agent cannot tell whether it is wrong.
     *
     * @param  array<int, string>  $guids
     */
    private function adMitgliederAbgleichen(ADGroup $group, int $customerId, array $guids): void
    {
        $soll = ADUser::where('customer_id', $customerId)
            ->whereIn('agent_identifier', $guids)
            ->pluck('id')
            ->all();

        $vonHand = $group->users()->whereNull('agent_identifier')->pluck('ad_users.id')->all();

        $group->users()->sync(array_merge($soll, $vonHand));
    }

    /**
     * The AD user or group a reported object belongs to.
     *
     * First by objectGUID (agent_identifier) - survives renames. Then by
     * name, but only among entries without a GUID: someone documented the
     * user by hand before the agent ran, and this run adopts that entry
     * instead of creating a second one. Case-insensitive, as AD is.
     */
    private function findAdEntry(string $klasse, int $customerId, string $identifier, string $namensfeld, ?string $name): ADUser|ADGroup
    {
        $eintrag = $klasse::where('customer_id', $customerId)->where('agent_identifier', $identifier)->first();

        if (! $eintrag && filled($name)) {
            $eintrag = $klasse::where('customer_id', $customerId)
                ->whereNull('agent_identifier')
                ->whereRaw('LOWER('.$namensfeld.') = ?', [mb_strtolower($name)])
                ->orderBy('id')
                ->first();
        }

        return $eintrag ?? new $klasse(['customer_id' => $customerId]);
    }

    /** "Windows2016Domain" -> "2016", "Windows2012R2Domain" -> "2012R2". */
    private function adFunktionsebene(?string $modus): ?string
    {
        if (! $modus || ! preg_match('/(\d{4})(R2)?/i', $modus, $treffer)) {
            return null;
        }

        $schluessel = $treffer[1].(empty($treffer[2]) ? '' : 'R2');

        return array_key_exists($schluessel, config('custom.ad_functional_levels')) ? $schluessel : null;
    }

    /** All five roles on one DC is the rule - then one name instead of five. */
    private function adFsmo(array $fsmo): ?string
    {
        $kurz = collect($fsmo)->map(fn ($host) => $host ? strtoupper(strtok($host, '.')) : null)->filter();

        if ($kurz->isEmpty()) {
            return null;
        }

        if ($kurz->count() === 5 && $kurz->unique()->count() === 1) {
            return $kurz->first().' (alle 5 Rollen)';
        }

        $namen = ['pdc' => 'PDC', 'rid' => 'RID', 'infrastructure' => 'Infrastruktur', 'schema' => 'Schema', 'naming' => 'Domänennamen'];

        return $kurz->map(fn ($host, $rolle) => ($namen[$rolle] ?? $rolle).': '.$host)->implode(', ');
    }

    /**
     * A documented server or VM by host name: "dc01.firma.local" and "DC01"
     * both find "dc01". Servers first - a DC is more often hardware.
     */
    private function machineForHostname(int $customerId, string $hostname): Server|VM|null
    {
        $kurz = strtolower(strtok(trim($hostname), '.'));

        foreach ([Server::class, VM::class] as $klasse) {
            $treffer = $klasse::where('customer_id', $customerId)
                ->whereRaw('LOWER(name) = ?', [$kurz])
                ->first();

            if ($treffer) {
                return $treffer;
            }
        }

        return null;
    }
}
