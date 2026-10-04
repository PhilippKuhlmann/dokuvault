<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Accesspoint;
use App\Models\ADDomain;
use App\Models\ADDomainHost;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\AgentInstallation;
use App\Models\Backup;
use App\Models\Computer;
use App\Models\Domain;
use App\Models\LicenseSoftware;
use App\Models\Mailbox;
use App\Models\MailboxProvider;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\Service;
use App\Models\VM;
use App\Models\Wifi;
use App\Support\AgentSkript;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AgentController extends Controller
{
    /**
     * Windows-Rollen und -Rollendienste, die einem Dienst aus dem Katalog
     * entsprechen. Geprüft wird der sprachunabhängige Name, nicht der
     * übersetzte Anzeigename.
     *
     * Was hier nicht steht, wird verworfen: ein Windows Server bringt gut
     * hundert installierte Merkmale mit, von denen die meisten nichts über
     * seine Aufgabe aussagen. Und was hier steht, wird nur übernommen, wenn
     * der Dienstekatalog den Namen auch führt - der Agent legt keine neuen
     * Katalogeinträge an.
     */
    protected const WINDOWS_ROLLEN = [
        'AD-Domain-Services' => 'AD',
        'AD-Certificate' => 'PKI',
        'DNS' => 'DNS',
        'DHCP' => 'DHCP',
        'FS-FileServer' => 'Fileserver',
        'FS-DFS-Namespace' => 'DFS',
        'Print-Services' => 'Print',
        'Remote-Desktop-Services' => 'RDS',
        'Hyper-V' => 'Hyper-V',
        'Web-Server' => 'IIS',
        'UpdateServices' => 'WSUS',
        'WDS' => 'WDS',
        'RemoteAccess' => 'VPN',
    ];

    /**
     * Running systemd units of a Linux server that match a service of the
     * catalog - like WINDOWS_ROLLEN, only what the catalog knows is kept.
     */
    protected const LINUX_DIENSTE = [
        'apache2' => 'apache2',
        'httpd' => 'apache2',
        'nginx' => 'nginx',
        'docker' => 'docker',
        'mariadb' => 'mariadb',
        'mysql' => 'SQL',
        'postgresql' => 'SQL',
        'named' => 'DNS',
        'bind9' => 'DNS',
        'smbd' => 'Fileserver',
        'nfs-server' => 'Fileserver',
        'cups' => 'Print',
        'isc-dhcp-server' => 'DHCP',
        'kea-dhcp4-server' => 'DHCP',
    ];

    /**
     * Was /etc/os-release meldet, auf den Betriebssystem-Katalog abgebildet:
     * 'debian' + '12' wird "Debian 12".
     *
     * Ausgeschrieben und nicht geraten. Was hier nicht steht, bleibt ohne
     * Betriebssystem - ein leeres Feld ist besser als ein falsches: "Debian
     * 12" und "Debian 13" haben verschiedene Support-Enden, und genau danach
     * wird dieses Feld gelesen.
     *
     * Die Zahl sagt, wie viele Stellen der Version der Katalog fuehrt -
     * "Debian 12", aber "Ubuntu Server 24.04 LTS".
     */
    protected const OS_RELEASE_KATALOG = [
        'debian' => ['Debian %s', 1],
        'ubuntu' => ['Ubuntu Server %s LTS', 2],
        'rocky' => ['Rocky Linux %s', 1],
        'almalinux' => ['AlmaLinux %s', 1],
        'opensuse-leap' => ['openSUSE Leap %s', 1],
    ];

    /**
     * Nimmt die von einem Proxmox-Host gemeldeten Daten entgegen und legt
     * den Host als Server sowie seine VMs/LXC-Container als VM-Einträge an
     * bzw. aktualisiert sie (Upsert über agent_identifier). Es wird nichts
     * gelöscht.
     */
    public function proxmox(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $site = $request->attributes->get('agentSite');

        $data = $request->validate(array_merge($this->hostRegeln(), $this->gastRegeln(), [
            'host.pve_version' => ['nullable', 'string', 'max:255'],
            'host.kernel' => ['nullable', 'string', 'max:255'],
            'host.cpu' => ['nullable', 'string', 'max:255'],
            'host.memory_gb' => ['nullable', 'numeric'],
            'host.storages' => ['nullable', 'array'],
            'host.storages.*.name' => ['nullable', 'string', 'max:255'],
            'host.storages.*.type' => ['nullable', 'string', 'max:255'],
            'host.storages.*.total_gb' => ['nullable', 'numeric'],
            'host.storages.*.used_gb' => ['nullable', 'numeric'],
        ], $this->backupRegeln('backups')));

        // Versionsspezifisch ("Proxmox VE 8" statt nur "Proxmox VE"): Version
        // 7/8/9 haben unterschiedliche Support-Enden, ein Sammel-Eintrag
        // haette das nicht abbilden koennen.
        //
        // nurKatalog: Dieser Agent legt keine Betriebssysteme an. Er meldet
        // Bausteine ('debian', '12'), keine fertigen Namen - was sich nicht
        // eindeutig einem Katalogeintrag zuordnen laesst, bleibt leer.
        [$server, $guestCount] = $this->hostUndGaeste(
            $data['host'], $data['guests'] ?? [], $customer, $site,
            $this->mapPveVersion($data['host']['pve_version'] ?? null),
            nurKatalog: true
        );

        // vzdump jobs of the cluster (since 26.10.03; older scripts send none).
        $backupCount = $this->backupsDokumentieren($customer->id, $data['backups'] ?? []);

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'site' => $site->name,
            'server' => $server->name,
            'server_id' => $server->id,
            'guests_documented' => $guestCount,
            'backups_documented' => $backupCount,
        ]);
    }

    /**
     * Nimmt die von einem Hyper-V-Host gemeldeten Daten entgegen. Bis auf das
     * Betriebssystem des Hosts - Windows statt Proxmox VE - ist es dieselbe
     * Aufgabe wie bei proxmox(): ein Host, darunter seine Gäste.
     */
    public function hyperv(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $site = $request->attributes->get('agentSite');

        $data = $request->validate(array_merge($this->hostRegeln(), $this->gastRegeln(), [
            'host.os' => ['nullable', 'string', 'max:255'],
            'host.cpu' => ['nullable', 'string', 'max:255'],
            'host.memory_gb' => ['nullable', 'numeric'],
        ]));

        [$server, $guestCount] = $this->hostUndGaeste(
            $data['host'], $data['guests'] ?? [], $customer, $site,
            $this->osKatalogName($data['host']['os'] ?? null, 'Windows')
        );

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'site' => $site->name,
            'server' => $server->name,
            'server_id' => $server->id,
            'guests_documented' => $guestCount,
        ]);
    }

    /**
     * Nimmt die aus vCenter gemeldeten Daten entgegen - je Aufruf ein
     * ESXi-Host mit seinen VMs. Das Script meldet jeden Host einzeln, weil die
     * Zuordnung "welche VM läuft auf welchem Host" sonst verloren ginge.
     *
     * Anders als Proxmox und Hyper-V meldet vCenter weder Hersteller noch
     * Seriennummer des Hosts: die Schnittstelle gibt sie nicht heraus. Genau
     * dafür lässt hostUndGaeste() nicht gemeldete Felder unangetastet, statt
     * sie mit null zu überschreiben.
     */
    public function vmware(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $site = $request->attributes->get('agentSite');

        $data = $request->validate(array_merge($this->hostRegeln(), $this->gastRegeln(), [
            'host.os' => ['nullable', 'string', 'max:255'],
        ]));

        [$server, $guestCount] = $this->hostUndGaeste(
            $data['host'], $data['guests'] ?? [], $customer, $site,
            $this->osKatalogName($data['host']['os'] ?? null, 'VMware ESXi')
        );

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'site' => $site->name,
            'server' => $server->name,
            'server_id' => $server->id,
            'guests_documented' => $guestCount,
        ]);
    }

    /**
     * Nimmt die von einem Windows-Server gemeldeten Daten entgegen und legt
     * ihn als Server an - nicht als Computer. windowsClient() legt immer einen
     * Computer an; auf einem Server ausgeführt landete der Rechner damit unter
     * "Clients", wo ihn niemand sucht.
     *
     * Kennung ist wie beim Client die MachineGuid. Der Hyper-V-Agent meldet
     * dieselbe: läuft beides auf demselben Blech, bleibt es ein Server-Eintrag.
     */
    public function windowsServer(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $site = $request->attributes->get('agentSite');

        $data = $request->validate([
            'server.identifier' => ['required', 'string', 'max:255'],
            'server.hostname' => ['required', 'string', 'max:255'],
            'server.manufacturer' => ['nullable', 'string', 'max:255'],
            'server.model' => ['nullable', 'string', 'max:255'],
            'server.serial' => ['nullable', 'string', 'max:255'],
            'server.os' => ['nullable', 'string', 'max:255'],
            'server.ip' => ['nullable', 'string', 'max:255'],
            'server.cpu' => ['nullable', 'string', 'max:255'],
            'server.memory_gb' => ['nullable', 'numeric'],
            'server.roles' => ['nullable', 'array', 'max:500'],
            'server.roles.*' => ['string', 'max:255'],
        ]);

        [$server] = $this->hostUndGaeste(
            $data['server'], [], $customer, $site,
            $this->osKatalogName($data['server']['os'] ?? null, 'Windows')
        );

        $dienste = $this->diensteAusRollen($data['server']['roles'] ?? []);

        // Nur eintragen, solange das Feld leer ist. Wer die Dienste einmal von
        // Hand gepflegt hat, weiss mehr als Get-WindowsFeature - der naechste
        // Lauf darf das nicht ueberschreiben.
        if ($dienste !== [] && blank($server->getRawOriginal('services'))) {
            $server->update(['services' => implode(',', $dienste)]);
        }

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'site' => $site->name,
            'server' => $server->name,
            'server_id' => $server->id,
            'services_documented' => count($dienste),
        ]);
    }

    /**
     * A Linux server (Debian/Ubuntu agent, script linux-server.sh).
     *
     * Hardware becomes a server. A virtual machine is usually documented
     * already - by the Proxmox or Hyper-V agent, under the name of its
     * hypervisor - so it is looked up by host name and completed instead of
     * appearing a second time as a server. Only without a match it is
     * created as a VM of its own.
     */
    public function linuxServer(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $site = $request->attributes->get('agentSite');

        $data = $request->validate([
            'server.identifier' => ['required', 'string', 'max:255'],
            'server.hostname' => ['required', 'string', 'max:255'],
            'server.manufacturer' => ['nullable', 'string', 'max:255'],
            'server.model' => ['nullable', 'string', 'max:255'],
            'server.serial' => ['nullable', 'string', 'max:255'],
            'server.ip' => ['nullable', 'string', 'max:255'],
            'server.os_id' => ['nullable', 'string', 'max:50'],
            'server.os_version' => ['nullable', 'string', 'max:50'],
            'server.virtual' => ['nullable', 'boolean'],
            'server.cpu' => ['nullable', 'string', 'max:255'],
            'server.memory_gb' => ['nullable', 'numeric'],
            'server.services' => ['nullable', 'array', 'max:100'],
            'server.services.*' => ['string', 'max:100'],
        ]);
        $meldung = $data['server'];

        // No catalog entry, no operating system: the support end hangs on it.
        $osName = $this->osReleaseKatalogName($meldung['os_id'] ?? null, $meldung['os_version'] ?? null);
        $dienste = collect($meldung['services'] ?? [])
            ->map(fn ($unit) => self::LINUX_DIENSTE[$unit] ?? null)
            ->filter()
            ->unique()
            ->intersect(Service::pluck('name'))
            ->values()
            ->all();

        if ($meldung['virtual'] ?? false) {
            $kurz = mb_strtolower(explode('.', $meldung['hostname'])[0]);
            $geraet = VM::where('customer_id', $customer->id)->where('agent_identifier', $meldung['identifier'])->first()
                ?? VM::where('customer_id', $customer->id)
                    ->where(fn ($q) => $q->whereRaw('LOWER(name) = ?', [$kurz])
                        ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($meldung['hostname'])]))
                    ->orderBy('id')
                    ->first()
                ?? new VM([
                    'customer_id' => $customer->id,
                    'site_id' => $site->id,
                    'name' => $meldung['hostname'],
                    'agent_identifier' => $meldung['identifier'],
                ]);

            if ($osId = $this->betriebssystemId($osName, true)) {
                $geraet->operating_system_id = $osId;
            }
            $geraet->save();
            $this->meldeAdresse($geraet, $customer->id, $site->id, $meldung['ip'] ?? null);
        } else {
            [$geraet] = $this->hostUndGaeste($meldung, [], $customer, $site, (string) $osName, nurKatalog: true);
        }

        // As with Windows: only into an empty field - whoever maintained the
        // services by hand knows more than systemctl.
        if ($dienste !== [] && blank($geraet->getRawOriginal('services'))) {
            $geraet->update(['services' => implode(',', $dienste)]);
        }

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'type' => $geraet instanceof VM ? 'vm' : 'server',
            'name' => $geraet->name,
            'services_documented' => count($dienste),
        ]);
    }

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
            $user = $this->adEintragFinden(ADUser::class, $customer->id, $u['identifier'], 'username', $u['username'] ?? null);
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
            $group = $this->adEintragFinden(ADGroup::class, $customer->id, $g['identifier'], 'name', $g['name'] ?? null);
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
     * Backup jobs from Veeam B&R or the Windows Server-Sicherung (scripts
     * veeam.ps1, windows-backup.ps1). Proxmox sends its vzdump jobs with
     * the host report instead.
     */
    public function backup(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $data = $request->validate($this->backupRegeln('jobs'));

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'backups_documented' => $this->backupsDokumentieren($customer->id, $data['jobs'] ?? []),
        ]);
    }

    /** Validation for a list of reported backup jobs under $feld. */
    private function backupRegeln(string $feld): array
    {
        return [
            $feld => ['nullable', 'array', 'max:500'],
            $feld.'.*.identifier' => ['required', 'string', 'max:255'],
            $feld.'.*.name' => ['nullable', 'string', 'max:255'],
            $feld.'.*.software' => ['nullable', 'string', 'max:255'],
            $feld.'.*.source' => ['nullable', 'string', 'max:255'],
            $feld.'.*.destination' => ['nullable', 'string', 'max:255'],
            $feld.'.*.schedule' => ['nullable', 'string', 'max:255'],
            $feld.'.*.retention' => ['nullable', 'string', 'max:255'],
            $feld.'.*.last_status' => ['nullable', 'in:'.implode(',', array_keys(Backup::STATUS))],
            $feld.'.*.last_run_at' => ['nullable', 'date'],
            $feld.'.*.last_success' => ['nullable', 'date'],
            // Recent runs (since 26.10.03) - the history on the admin overview.
            $feld.'.*.runs' => ['nullable', 'array', 'max:100'],
            $feld.'.*.runs.*.status' => ['required', 'in:'.implode(',', array_keys(Backup::STATUS))],
            $feld.'.*.runs.*.finished_at' => ['required', 'date'],
        ];
    }

    /**
     * Create or update reported backup jobs. Found by agent_identifier, else
     * a job documented by hand with the same name is adopted (as with AD
     * users). Only what was reported is written - notes and password stay.
     * Nothing is deleted: a job removed in Veeam stays until someone
     * removes it here.
     */
    private function backupsDokumentieren(int $customerId, array $jobs): int
    {
        foreach ($jobs as $job) {
            $backup = Backup::where('customer_id', $customerId)->where('agent_identifier', $job['identifier'])->first();

            if (! $backup && filled($job['name'] ?? null)) {
                $backup = Backup::where('customer_id', $customerId)
                    ->whereNull('agent_identifier')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($job['name'])])
                    ->orderBy('id')
                    ->first();
            }

            $backup ??= new Backup(['customer_id' => $customerId]);

            $backup->fill(array_filter([
                'agent_identifier' => $job['identifier'],
                'name' => $job['name'] ?? null,
                'software' => $job['software'] ?? null,
                'source' => $job['source'] ?? null,
                'destination' => $job['destination'] ?? null,
                'schedule' => $job['schedule'] ?? null,
                'retention' => $job['retention'] ?? null,
                'last_status' => $job['last_status'] ?? null,
                'last_run_at' => filled($job['last_run_at'] ?? null) ? Carbon::parse($job['last_run_at']) : null,
                'last_success' => filled($job['last_success'] ?? null) ? Carbon::parse($job['last_success'])->toDateString() : null,
            ], fn ($wert) => filled($wert)));

            // A name is needed for the list; a job without one is named by
            // its software.
            $backup->name ??= ($job['software'] ?? 'Backup');
            $backup->save();

            // The history: the runs sent along, and the last run itself - a
            // script without "runs" still builds up a history over time.
            $runs = $job['runs'] ?? [];
            if (filled($job['last_run_at'] ?? null) && filled($job['last_status'] ?? null)) {
                $runs[] = ['status' => $job['last_status'], 'finished_at' => $job['last_run_at']];
            }
            $backup->recordRuns($runs);
        }

        return count($jobs);
    }

    /**
     * The outcome of a run, per role: shown on the agent page and the
     * dashboard. Only for a machine that checked in with this customer.
     */
    public function report(Request $request)
    {
        $data = $request->validate([
            'machine_id' => ['required', 'string', 'max:100'],
            'results' => ['required', 'array', 'max:50'],
            'results.*.role' => ['required', 'string', 'max:50'],
            'results.*.ok' => ['required', 'boolean'],
            // Long output is cut to its end by the model, not refused.
            'results.*.message' => ['nullable', 'string', 'max:100000'],
        ]);

        $installation = AgentInstallation::where('customer_id', $request->attributes->get('agentCustomer')->id)
            ->where('machine_id', strtolower($data['machine_id']))
            ->firstOrFail();

        $installation->recordResults($data['results']);

        return response()->json(['status' => 'ok']);
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
    private function adEintragFinden(string $klasse, int $customerId, string $identifier, string $namensfeld, ?string $name): ADUser|ADGroup
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

    /**
     * The current PowerShell script of an agent, with URL and token filled in.
     *
     * Called by the Windows service (agent/windows-service) before every run:
     * so script updates reach installed services without anyone downloading
     * anything. The token is the one the service authenticated with - the
     * plain value is only known here because it came in the header.
     *
     * Only agents the service can run unattended (AgentSkript::fuerDienst);
     * everything else is a 404, not a hint that it exists.
     */
    public function script(Request $request, string $agent)
    {
        // ?shell=bash for the Proxmox agent; without it PowerShell, as the
        // Windows service has always asked.
        $shell = $request->query('shell') === 'bash' ? 'bash' : 'powershell';
        $variante = AgentSkript::fuerDienst($shell)[$agent] ?? null;
        abort_unless($variante, 404);

        $token = $request->bearerToken() ?: $request->header('X-Agent-Token');

        return response(
            AgentSkript::rendern($variante, config('custom.agenten')[$agent]['endpunkt'], $token),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store']
        );
    }

    /**
     * Self-update of an installed agent: asked before every run with the
     * version it has. Same version: 204. Otherwise the current one.
     *
     * windows: the exe, with SHA-256 in a header - the service checks it
     * before replacing itself. proxmox: the installer, rendered with the token
     * the agent authenticated with; run.sh applies it with --update.
     */
    public function update(Request $request, string $dienst)
    {
        $gemeldet = (string) $request->query('version', '');

        if ($dienst === 'windows') {
            $aktuell = AgentSkript::exeVersion();
            $datei = public_path('downloads/dokuvault-agent.exe');
            abort_unless($aktuell && is_file($datei), 404);

            if ($gemeldet === $aktuell) {
                return response()->noContent();
            }

            return response()->file($datei, [
                'Content-Type' => 'application/vnd.microsoft.portable-executable',
                'X-Agent-Version' => $aktuell,
                'X-Agent-Sha256' => hash_file('sha256', $datei),
                'Cache-Control' => 'no-store',
            ]);
        }

        // proxmox, linux: one installer, rendered with the kind asked for.
        $aktuell = AgentSkript::installerVersion('linux-agent.sh');
        if ($gemeldet === $aktuell) {
            return response()->noContent();
        }

        $token = $request->bearerToken() ?: $request->header('X-Agent-Token');

        return response(AgentSkript::rendernInstaller('linux-agent.sh', $token, $dienst), 200, [
            'Content-Type' => 'text/x-shellscript; charset=utf-8',
            'X-Agent-Version' => $aktuell,
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * An installed agent reports in every few minutes: which machine, what it
     * detected. The answer is the roles it should run and whether to run now - switched on and off
     * on the agent page, so two DCs can both report as servers while only
     * one reports the domain.
     */
    public function checkin(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'in:'.implode(',', array_keys(config('custom.dienste', [])))],
            'machine_id' => ['required', 'string', 'max:100'],
            'hostname' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:100'],
            'detected' => ['array'],
            'detected.*' => ['string', 'max:50'],
            'interval' => ['nullable', 'integer', 'min:5', 'max:10080'],
        ]);

        $installation = AgentInstallation::checkin(
            $request->attributes->get('agentToken'),
            $data['kind'],
            strtolower($data['machine_id']),
            $data['hostname'],
            $data['domain'] ?? null,
            $data['version'] ?? null,
            $data['detected'] ?? [],
            $data['interval'] ?? null,
        );

        // run: whether to run now (interval due or "Jetzt melden"). Agents
        // from before 26.10.03 do not know it and run on their own timer.
        return response()->json([
            'roles' => $installation->roles,
            'run' => $installation->takeDueRun(),
            'interval' => $installation->intervalMinutes(),
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
                $maschine = $this->maschineZumHostnamen($customer->id, $hostname);
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
     * Manufacturer, model and serial as reported - without BIOS placeholders.
     *
     * Desktop and whitebox boards leave "System manufacturer", "To Be Filled
     * By O.E.M." and the like in the DMI fields; the agent reads them
     * correctly, they just mean nothing. Such a value is not written - and if
     * an earlier run already stored one, it is cleared. A real value entered
     * by hand stays when the agent has nothing better.
     *
     * @return array<string, string|null>
     */
    private function hardwareFelder(array $gemeldet, $vorhanden): array
    {
        $felder = [];

        foreach (['manufacturer' => 'manufacturer', 'model' => 'model', 'serial' => 'serialNumber'] as $quelle => $spalte) {
            if (! array_key_exists($quelle, $gemeldet)) {
                continue;
            }

            $wert = trim((string) $gemeldet[$quelle]);

            if ($wert !== '' && ! $this->istPlatzhalter($wert)) {
                $felder[$spalte] = $wert;
            } elseif ($vorhanden && $this->istPlatzhalter((string) $vorhanden->{$spalte})) {
                $felder[$spalte] = null;
            }
        }

        return $felder;
    }

    private function istPlatzhalter(string $wert): bool
    {
        $wert = strtolower(trim($wert));

        if ($wert === '') {
            return false;
        }

        // Only zeros, X, dots, dashes: "0000000000", "XXXXXXXX", "-".
        if (preg_match('/^[0x\s.\-_]+$/i', $wert)) {
            return true;
        }

        return in_array($wert, [
            'system manufacturer', 'system product name', 'system serial number', 'system version',
            'to be filled by o.e.m.', 'to be filled by oem', 'default string', 'default',
            'not specified', 'not applicable', 'not available', 'none', 'n/a', 'na', 'oem', 'o.e.m.',
            'unknown', 'undefined', 'invalid', '0123456789', '123456789', '1234567890',
            'base board serial number', 'chassis manufacturer', 'chassis serial number',
            'type1productconfigid', 'sku', 'all series',
        ], true);
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
    private function maschineZumHostnamen(int $customerId, string $hostname): Server|VM|null
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

    /**
     * Nimmt die von einem Windows-Arbeitsplatzrechner gemeldeten Daten
     * entgegen und legt ihn als Computer an bzw. aktualisiert ihn (Upsert
     * über agent_identifier = MachineGuid). Laeuft auf jedem Windows-PC,
     * anders als windowsAd() ohne RSAT/AD-Modul.
     */
    public function windowsClient(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $site = $request->attributes->get('agentSite');

        $data = $request->validate([
            'client.identifier' => ['required', 'string', 'max:255'],
            'client.hostname' => ['required', 'string', 'max:255'],
            'client.manufacturer' => ['nullable', 'string', 'max:255'],
            'client.model' => ['nullable', 'string', 'max:255'],
            'client.serial' => ['nullable', 'string', 'max:255'],
            'client.os' => ['nullable', 'string', 'max:255'],
            'client.ip' => ['nullable', 'string', 'max:255'],
        ]);

        $client = $data['client'];

        $os = OperatingSystem::firstOrCreate(['name' => $this->osKatalogName($client['os'] ?? null, 'Windows')]);

        $vorhanden = Computer::where('customer_id', $customer->id)->where('agent_identifier', $client['identifier'])->first();

        $computer = Computer::updateOrCreate(
            ['customer_id' => $customer->id, 'agent_identifier' => $client['identifier']],
            [
                'site_id' => $site->id,
                'operating_system_id' => $os->id,
                'name' => $client['hostname'],
            ] + $this->hardwareFelder($client, $vorhanden)
        );

        $this->meldeAdresse($computer, $customer->id, $site->id, $client['ip'] ?? null);

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'site' => $site->name,
            'client' => $computer->name,
            'client_id' => $computer->id,
        ]);
    }

    /**
     * Nimmt die von einem UniFi-Controller gemeldeten Switches, Accesspoints
     * und WLANs entgegen (Upsert über agent_identifier = MAC bzw. UniFi-Id).
     *
     * Das WLAN-Kennwort meldet das Script bewusst nicht - wie beim AD-Agenten
     * bleiben Kennwörter allein manuell gepflegt. Auch das VLAN bleibt leer:
     * welches der gepflegten Netze hinter einer SSID steht, weiß der
     * Controller nicht.
     */
    public function unifi(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $site = $request->attributes->get('agentSite');

        $data = $request->validate([
            'site' => ['nullable', 'string', 'max:255'],
            'switches' => ['nullable', 'array'],
            'switches.*.identifier' => ['required_with:switches', 'string', 'max:255'],
            'switches.*.name' => ['required_with:switches', 'string', 'max:255'],
            'switches.*.manufacturer' => ['nullable', 'string', 'max:255'],
            'switches.*.model' => ['nullable', 'string', 'max:255'],
            'switches.*.serial' => ['nullable', 'string', 'max:255'],
            'switches.*.ip' => ['nullable', 'string', 'max:255'],
            'switches.*.dhcp' => ['nullable', 'boolean'],
            'accesspoints' => ['nullable', 'array'],
            'accesspoints.*.identifier' => ['required_with:accesspoints', 'string', 'max:255'],
            'accesspoints.*.name' => ['required_with:accesspoints', 'string', 'max:255'],
            'accesspoints.*.manufacturer' => ['nullable', 'string', 'max:255'],
            'accesspoints.*.model' => ['nullable', 'string', 'max:255'],
            'accesspoints.*.serial' => ['nullable', 'string', 'max:255'],
            'accesspoints.*.ip' => ['nullable', 'string', 'max:255'],
            'accesspoints.*.dhcp' => ['nullable', 'boolean'],
            'wifis' => ['nullable', 'array'],
            'wifis.*.identifier' => ['required_with:wifis', 'string', 'max:255'],
            'wifis.*.ssid' => ['required_with:wifis', 'string', 'max:255'],
            'wifis.*.encryption' => ['nullable', 'string', 'max:255'],
            'wifis.*.password' => ['nullable', 'string', 'max:255'],
        ]);

        $switches = 0;
        foreach ($data['switches'] ?? [] as $g) {
            $this->meldeNetzwerkgeraet(NetworkSwitch::class, $g, $customer, $site);
            $switches++;
        }

        $accesspoints = 0;
        foreach ($data['accesspoints'] ?? [] as $g) {
            $this->meldeNetzwerkgeraet(Accesspoint::class, $g, $customer, $site);
            $accesspoints++;
        }

        $wlans = 0;
        foreach ($data['wifis'] ?? [] as $w) {
            $wlan = Wifi::firstOrNew([
                'customer_id' => $customer->id,
                'agent_identifier' => $w['identifier'],
            ]);

            $wlan->fill([
                'site_id' => $site->id,
                'ssid' => $w['ssid'],
                'encryption' => $w['encryption'] ?? null,
                // 'network_id' bleibt unangetastet: welches der gepflegten
                // VLANs hinter der SSID steht, weiss der Controller nicht.
            ]);

            /*
             * Die Passphrase nur setzen, wenn der Controller wirklich eine
             * gemeldet hat. Ein WPA-Enterprise-WLAN hat keine - dort stuende
             * sonst null, wo vorher etwas Richtiges stand. Und der Setter
             * verschluesselt bedingungslos; null waere ein Typfehler.
             *
             * Und nur, wenn sie sich unterscheidet: Crypt::encryptString
             * erzeugt bei jedem Aufruf einen anderen Chiffretext. Ohne den
             * Vergleich waere die Zeile bei jedem Lauf "geaendert", obwohl
             * sich nichts geaendert hat.
             */
            if (filled($w['password'] ?? null) && $wlan->password !== $w['password']) {
                $wlan->password = $w['password'];
            }

            $wlan->save();
            $wlans++;
        }

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'site' => $site->name,
            'switches_documented' => $switches,
            'accesspoints_documented' => $accesspoints,
            'wifis_documented' => $wlans,
        ]);
    }

    /**
     * Nimmt die aus Microsoft Graph gemeldeten Postfächer, Domains und
     * Lizenzen entgegen (Upsert über agent_identifier = Objekt-Id im Tenant).
     *
     * Ohne Standort: Postfächer, Domains und Lizenzen hängen am Kunden, nicht
     * an einem Ort - ein Postfach steht in keinem Serverraum.
     */
    public function microsoft365(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');

        $data = $request->validate([
            'tenant' => ['nullable', 'string', 'max:255'],
            'mailboxes' => ['nullable', 'array'],
            'mailboxes.*.identifier' => ['required_with:mailboxes', 'string', 'max:255'],
            'mailboxes.*.name' => ['nullable', 'string', 'max:255'],
            'mailboxes.*.mail' => ['nullable', 'string', 'max:255'],
            'mailboxes.*.username' => ['nullable', 'string', 'max:255'],
            'domains' => ['nullable', 'array'],
            'domains.*.identifier' => ['required_with:domains', 'string', 'max:255'],
            'domains.*.name' => ['required_with:domains', 'string', 'max:255'],
            'licences' => ['nullable', 'array'],
            'licences.*.identifier' => ['required_with:licences', 'string', 'max:255'],
            'licences.*.name' => ['required_with:licences', 'string', 'max:255'],
            'licences.*.gebucht' => ['nullable', 'integer'],
            'licences.*.belegt' => ['nullable', 'integer'],
        ]);

        $postfaecher = 0;
        if ($data['mailboxes'] ?? false) {
            // Wie proxmox() es mit dem Betriebssystem tut: den Anbieter einmal
            // anlegen und danach wiederverwenden. Die Serverangaben sind bei
            // Microsoft 365 fuer alle Tenants dieselben und in der Tabelle
            // Pflicht; gesetzt werden sie nur beim Anlegen, ein von Hand
            // geaenderter Eintrag bleibt also stehen.
            $anbieter = MailboxProvider::firstOrCreate(['name' => 'Microsoft 365'], [
                'pop3server' => 'outlook.office365.com',
                'pop3port' => '995',
                'imapserver' => 'outlook.office365.com',
                'imapport' => '993',
                'smtpserver' => 'smtp.office365.com',
                'smtpport' => '587',
            ]);

            foreach ($data['mailboxes'] as $m) {
                $postfach = Mailbox::firstOrNew([
                    'customer_id' => $customer->id,
                    'agent_identifier' => $m['identifier'],
                ]);

                // Nur beim Anlegen setzen: wer das Postfach spaeter einem
                // anderen Anbieter zugeordnet hat, wird nicht ueberstimmt.
                $postfach->mailbox_provider_id ??= $anbieter->id;

                $postfach->fill([
                    'name' => $m['name'] ?? null,
                    'mailAdress' => $m['mail'] ?? null,
                    'username' => $m['username'] ?? null,
                    // 'password' bleibt unangetastet (manuell gepflegt)
                ])->save();

                $postfaecher++;
            }
        }

        $domains = 0;
        foreach ($data['domains'] ?? [] as $d) {
            Domain::updateOrCreate(
                ['customer_id' => $customer->id, 'agent_identifier' => $d['identifier']],
                ['name' => $d['name']]
            );
            $domains++;
        }

        $lizenzen = 0;
        foreach ($data['licences'] ?? [] as $l) {
            LicenseSoftware::updateOrCreate(
                ['customer_id' => $customer->id, 'agent_identifier' => $l['identifier']],
                [
                    'name' => $this->lizenzName($l),
                    'abo' => true,
                    // 'key' bleibt leer: einen Schluessel gibt es bei einem
                    // Microsoft-365-Abonnement nicht.
                ]
            );
            $lizenzen++;
        }

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'tenant' => $data['tenant'] ?? null,
            'mailboxes_documented' => $postfaecher,
            'domains_documented' => $domains,
            'licences_documented' => $lizenzen,
        ]);
    }

    /**
     * Legt einen Host als Server an und seine Gäste als VMs bzw. aktualisiert
     * sie. Proxmox, Hyper-V, VMware und der Windows-Server-Agent tun genau
     * das; nur die Herkunft des Betriebssystemnamens unterscheidet sie.
     *
     * @return array{0: Server, 1: int} Server und Zahl der gemeldeten Gäste
     */
    protected function hostUndGaeste(array $host, array $gaeste, $customer, $site, string $hostOs, bool $nurKatalog = false): array
    {
        // Hinweis: 'services' wird hier NICHT gesetzt - das Feld pflegt der
        // Nutzer manuell (Rollen wie AD, FS, DNS, DHCP ...). Allein
        // windowsServer() traegt etwas ein, und auch nur in ein leeres Feld.
        $attribute = [
            'site_id' => $site->id,
            'name' => $host['hostname'],
        ];

        // Nur eintragen, wenn es einen Treffer gibt. Ein null loeschte sonst
        // bei jedem Lauf, was jemand von Hand nachgetragen hat.
        if ($osId = $this->betriebssystemId($hostOs, $nurKatalog)) {
            $attribute['operating_system_id'] = $osId;
        }

        // Nur gemeldete Felder schreiben. vCenter gibt Hersteller, Modell und
        // Seriennummer nicht heraus - wuerde hier stur null eingetragen, loeschte
        // jeder Lauf, was jemand von Hand nachgetragen hat.
        $vorhanden = Server::where('customer_id', $customer->id)->where('agent_identifier', $host['identifier'])->first();
        $attribute += $this->hardwareFelder($host, $vorhanden);

        $server = Server::updateOrCreate(
            ['customer_id' => $customer->id, 'agent_identifier' => $host['identifier']],
            $attribute
        );

        // Die IP ist keine Spalte am Geraet mehr, sondern ein Eintrag im Block
        // "Weitere IP-Adressen".
        $this->meldeAdresse($server, $customer->id, $site->id, $host['ip'] ?? null);

        $anzahl = 0;
        foreach ($gaeste as $gast) {
            $vmAttribute = [
                'site_id' => $site->id,
                'server_id' => $server->id,
                'name' => $gast['name'] ?? ('VM '.($gast['vmid'] ?? '')),
                // 'services' bleibt manuell (Rollen der VM)
            ];

            // Proxmox meldet, was im Gast in /etc/os-release steht ('debian',
            // '12'); die anderen einen fertigen Namen ("Windows Server 2022").
            $gastOsName = $nurKatalog
                ? $this->osReleaseKatalogName($gast['os_id'] ?? null, $gast['os_version'] ?? null)
                : $this->osKatalogName($gast['os'] ?? null, 'Unbekannt');

            if ($gastOsId = $this->betriebssystemId($gastOsName, $nurKatalog)) {
                $vmAttribute['operating_system_id'] = $gastOsId;
            }

            $vm = VM::updateOrCreate(
                ['customer_id' => $customer->id, 'agent_identifier' => $gast['identifier']],
                $vmAttribute
            );

            $this->meldeAdresse($vm, $customer->id, $site->id, $gast['ip'] ?? null);
            $anzahl++;
        }

        return [$server, $anzahl];
    }

    /**
     * Legt einen Switch bzw. Accesspoint an oder aktualisiert ihn.
     *
     * @param  class-string  $klasse
     */
    protected function meldeNetzwerkgeraet(string $klasse, array $g, $customer, $site): void
    {
        $geraet = $klasse::updateOrCreate(
            ['customer_id' => $customer->id, 'agent_identifier' => $g['identifier']],
            [
                'site_id' => $site->id,
                'name' => $g['name'],
                'manufacturer' => $g['manufacturer'] ?? null,
                'model' => $g['model'] ?? null,
                'serialNumber' => $g['serial'] ?? null,
            ]
        );

        // Ob die Adresse fest steht oder vom DHCP kommt, gehoert an die
        // Adresse und nicht an das Geraet: Ein Switch kann mehrere haben.
        $this->meldeAdresse($geraet, $customer->id, $site->id, $g['ip'] ?? null, $this->bezugsweg($g));
    }

    /**
     * Wie das Geraet zu seiner Adresse kommt.
     *
     * null, wenn der Controller nichts dazu sagt - etwa aeltere Firmware ohne
     * config_network. Dann bleibt der gespeicherte Stand, wie er ist: "nicht
     * gemeldet" ist nicht dasselbe wie "fest konfiguriert".
     */
    protected function bezugsweg(array $geraet): ?bool
    {
        if (! array_key_exists('dhcp', $geraet) || $geraet['dhcp'] === null) {
            return null;
        }

        return (bool) $geraet['dhcp'];
    }

    /**
     * Übersetzt gemeldete Windows-Rollen in Dienste aus dem Katalog.
     *
     * Der Abgleich gegen die vorhandenen Dienste ist Absicht: der Agent legt
     * keinen Katalogeintrag an. Sonst stünden nach dem ersten Lauf Dienste in
     * der Auswahl, die niemand angelegt hat - und bei jedem Kunden andere.
     *
     * @param  array<int, string>  $rollen
     * @return array<int, string>
     */
    protected function diensteAusRollen(array $rollen): array
    {
        $gewuenscht = collect($rollen)
            ->map(fn ($rolle) => self::WINDOWS_ROLLEN[$rolle] ?? null)
            ->filter()
            ->unique();

        if ($gewuenscht->isEmpty()) {
            return [];
        }

        return $gewuenscht
            ->intersect(Service::pluck('name'))
            ->values()
            ->all();
    }

    /**
     * Baut den Namen der Lizenz. Die Stückzahl gehört mit hinein: die Tabelle
     * hat keine Spalte dafür, und "wie viele der gebuchten Lizenzen sind
     * eigentlich belegt?" ist beim Kunden die erste Frage. Der Eintrag gehört
     * dem Agenten (agent_identifier), der Name darf sich also mit jedem Lauf
     * an die tatsächliche Zahl anpassen.
     */
    protected function lizenzName(array $lizenz): string
    {
        $name = trim($lizenz['name']);

        if (! isset($lizenz['gebucht'])) {
            return $name;
        }

        return $name.' ('.($lizenz['belegt'] ?? 0).' von '.$lizenz['gebucht'].' belegt)';
    }

    /**
     * Traegt die gemeldete Adresse im Block "Weitere IP-Adressen" ein.
     *
     * Kein updateOrCreate: Der Agent meldet denselben Host wieder und wieder -
     * bei einer schon vorhandenen Adresse bleibt die Netz-Zuordnung deshalb
     * unangetastet, sonst wuerfe ein zweiter Lauf eine von Hand korrigierte
     * Zuordnung wieder um (Ueberlappende Netze sind selten, aber moeglich).
     * Nur bei der Neuanlage wird ein passendes Netz gesucht und gesetzt.
     */
    protected function meldeAdresse($geraet, int $customerId, ?int $siteId, ?string $adresse, ?bool $dhcp = null): void
    {
        $adresse = trim((string) $adresse);

        if ($adresse === '') {
            return;
        }

        // Die gemeldete Adresse dient hier nur noch dazu, das Netz zu finden.
        $netz = Network::fuerAdresse($customerId, $siteId, $adresse)?->id;

        if ($dhcp === true) {
            $this->meldeDhcp($geraet, $customerId, $netz);

            return;
        }

        $vorhanden = $geraet->ipAddresses()->where('address', $adresse)->first();

        if ($vorhanden) {
            $vorhanden->update(['customer_id' => $customerId]);

            // Die Bezeichnung bleibt in jedem Fall unberuehrt - sie gehoert
            // dem Nutzer.
            if ($dhcp === false && $vorhanden->istDhcp()) {
                $vorhanden->update(['dhcp' => false]);
            }

            return;
        }

        // The agent cannot tell (Proxmox, Hyper-V report a guest's address
        // without knowing where it came from), and someone set the device
        // to DHCP in this network: that stays. Before, every run added the
        // reported address again as a fixed one next to the DHCP entry - the
        // choice made by hand was undone within the hour.
        if ($dhcp === null && $geraet->ipAddresses()->where('dhcp', true)
            ->when($netz, fn ($q) => $q->where('network_id', $netz))->exists()) {
            return;
        }

        // Aus DHCP wurde eine feste Adresse: Die adresslose Zeile wird zur
        // festen, statt eine zweite danebenzustellen.
        if ($dhcp === false && $alt = $geraet->ipAddresses()->where('dhcp', true)->first()) {
            $alt->update([
                'customer_id' => $customerId,
                'network_id' => $netz,
                'address' => $adresse,
                'dhcp' => false,
            ]);

            return;
        }

        $geraet->ipAddresses()->create([
            'address' => $adresse,
            'customer_id' => $customerId,
            'network_id' => $netz,
            'dhcp' => false,
        ]);
    }

    /**
     * Ein per DHCP versorgtes Gerät: Netz ja, Adresse nein.
     *
     * Welche Adresse es gerade hat, ist morgen eine andere - sie zu speichern
     * hiesse, etwas festzuhalten, das nicht haelt. Was bleibt, ist das Netz.
     *
     * Erst die vorhandene DHCP-Zeile, dann die unter der gemeldeten Adresse:
     * So wird beim Wechsel von fest auf DHCP die alte Zeile umgewandelt, statt
     * dass Alt und Neu nebeneinander stehen bleiben.
     */
    protected function meldeDhcp($geraet, int $customerId, ?int $netz): void
    {
        $vorhanden = $geraet->ipAddresses()->where('dhcp', true)->first()
            ?: $geraet->ipAddresses()->whereNotNull('address')->first();

        if ($vorhanden) {
            $vorhanden->update([
                'customer_id' => $customerId,
                'network_id' => $netz,
                'address' => null,
                'dhcp' => true,
            ]);

            return;
        }

        $geraet->ipAddresses()->create([
            'address' => null,
            'customer_id' => $customerId,
            'network_id' => $netz,
            'dhcp' => true,
        ]);
    }

    /**
     * Die Regeln, die jeder Host-Meldung gemeinsam sind.
     *
     * @return array<string, array<int, string>>
     */
    protected function hostRegeln(): array
    {
        return [
            'host.identifier' => ['required', 'string', 'max:255'],
            'host.hostname' => ['required', 'string', 'max:255'],
            'host.manufacturer' => ['nullable', 'string', 'max:255'],
            'host.model' => ['nullable', 'string', 'max:255'],
            'host.serial' => ['nullable', 'string', 'max:255'],
            'host.ip' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Die Regeln, die jeder Gast-Meldung gemeinsam sind.
     *
     * @return array<string, array<int, string>>
     */
    protected function gastRegeln(): array
    {
        return [
            'guests' => ['nullable', 'array'],
            'guests.*.identifier' => ['required_with:guests', 'string', 'max:255'],
            'guests.*.name' => ['nullable', 'string', 'max:255'],
            'guests.*.vmid' => ['nullable', 'integer'],
            'guests.*.type' => ['nullable', 'string', 'max:32'],
            'guests.*.os_id' => ['nullable', 'string', 'max:64'],
            'guests.*.os_version' => ['nullable', 'string', 'max:32'],
            'guests.*.os' => ['nullable', 'string', 'max:255'],
            'guests.*.ip' => ['nullable', 'string', 'max:255'],
            'guests.*.status' => ['nullable', 'string', 'max:32'],
            'guests.*.cores' => ['nullable', 'integer'],
            'guests.*.memory_gb' => ['nullable', 'numeric'],
        ];
    }

    /**
     * Bringt einen gemeldeten Betriebssystemnamen auf die Schreibweise des
     * Katalogs.
     *
     * Win32_OperatingSystem.Caption liefert immer "Microsoft Windows ...";
     * der Katalog fuehrt Windows-Systeme ohne dieses Praefix (siehe Seeder,
     * z. B. "Windows Server 2012 R2 Standard"). Ohne das Kappen legte
     * firstOrCreate bei jedem Kunden eine zweite, nie zusammengefuehrte
     * Katalogzeile an statt die vorhandene "Windows 11 Pro" zu treffen.
     */
    protected function osKatalogName(?string $name, string $ersatz): string
    {
        $sauber = trim(preg_replace('/^Microsoft\s+/i', '', (string) $name));

        return $sauber !== '' ? $sauber : $ersatz;
    }

    /**
     * Die Id des Katalogeintrags zu einem Betriebssystemnamen.
     *
     * $nurKatalog trennt zwei Faelle: Ein Agent, der einen fertigen,
     * eindeutigen Namen meldet ("Windows Server 2022 Standard"), darf den
     * Katalog ergaenzen. Der Proxmox-Agent darf das nicht - er meldet
     * Bausteine, und aus ihnen entstuenden Sammel-Eintraege wie "Linux", die
     * kein Support-Ende haben und die niemand mehr auseinanderdividiert.
     */
    protected function betriebssystemId(?string $name, bool $nurKatalog): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return $nurKatalog
            ? OperatingSystem::where('name', $name)->value('id')
            : OperatingSystem::firstOrCreate(['name' => $name])->id;
    }

    /**
     * "debian" + "12" -> "Debian 12", anhand von OS_RELEASE_KATALOG.
     *
     * Was dort nicht steht, ergibt null - und damit kein Betriebssystem.
     */
    protected function osReleaseKatalogName(?string $id, ?string $version): ?string
    {
        $id = strtolower(trim((string) $id));
        $version = trim((string) $version);

        if ($version === '' || ! isset(self::OS_RELEASE_KATALOG[$id])) {
            return null;
        }

        [$vorlage, $stellen] = self::OS_RELEASE_KATALOG[$id];

        return sprintf($vorlage, implode('.', array_slice(explode('.', $version), 0, $stellen)));
    }

    /**
     * "8.2.4" -> "Proxmox VE 8". Ohne auswertbare Hauptversion (Script zu alt,
     * pveversion nicht verfuegbar) bleibt "Proxmox VE" uebrig - und weil der
     * Katalog nur die Versionen 7/8/9 fuehrt, findet sich dazu nichts und der
     * Server bleibt ohne Betriebssystem. Besser als eine geratene Version:
     * daran haengt das Support-Ende.
     */
    protected function mapPveVersion(?string $pveVersion): string
    {
        if ($pveVersion && preg_match('/^(\d+)/', $pveVersion, $treffer)) {
            return 'Proxmox VE '.$treffer[1];
        }

        return 'Proxmox VE';
    }
}
