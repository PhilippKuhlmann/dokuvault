<?php

namespace App\Http\Controllers\API\Agent;

use App\Http\Controllers\API\Agent\Concerns\ReportsMachines;
use App\Models\Computer;
use App\Models\Server;
use App\Models\Service;
use App\Models\VM;
use Illuminate\Http\Request;

/**
 * A server reports itself: Windows Server and Linux, with the roles and
 * services that match the service catalog.
 */
class ServerController extends AgentEndpoint
{
    use ReportsMachines;

    /**
     * Windows roles and role services that match a service of the catalog.
     * The language-independent name is checked, not the translated display
     * name.
     *
     * What is not listed here is dropped: a Windows Server comes with a good
     * hundred installed features, most of which say nothing about its job.
     * And what is listed is only taken over when the service catalog carries
     * the name as well - the agent creates no catalog entries.
     */
    protected const WINDOWS_ROLLEN = [
        'AD-Domain-Services' => 'AD',
        'AD-Certificate' => 'PKI',
        'DNS' => 'DNS',
        'DHCP' => 'DHCP',
        // Either spelling: the standard catalog (ServiceSeeder) says "FS",
        // older installations "Fileserver".
        'FS-FileServer' => ['FS', 'Fileserver'],
        'FS-DFS-Namespace' => 'DFS',
        'Print-Services' => 'Print',
        'Remote-Desktop-Services' => 'RDS',
        'Hyper-V' => 'Hyper-V',
        'Web-Server' => ['IIS', 'Web'],
        'UpdateServices' => 'WSUS',
        'WDS' => 'WDS',
        'RemoteAccess' => 'VPN',
    ];

    /**
     * Running systemd units of a Linux server that match a service of the
     * catalog - like WINDOWS_ROLLEN, only what the catalog knows is kept.
     */
    protected const LINUX_DIENSTE = [
        // The specific name where a catalog has it, else "Web" of the
        // standard catalog (ServiceSeeder).
        'apache2' => ['apache2', 'Web'],
        'httpd' => ['apache2', 'Web'],
        'nginx' => ['nginx', 'Web'],
        'docker' => 'docker',
        'mariadb' => 'mariadb',
        'mysql' => 'SQL',
        'postgresql' => 'SQL',
        'named' => 'DNS',
        'bind9' => 'DNS',
        'smbd' => ['FS', 'Fileserver'],
        'nfs-server' => ['FS', 'Fileserver'],
        'cups' => 'Print',
        'isc-dhcp-server' => 'DHCP',
        'kea-dhcp4-server' => 'DHCP',
    ];

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
            $this->osCatalogName($data['server']['os'] ?? null, 'Windows')
        );

        $dienste = $this->servicesFromRoles($data['server']['roles'] ?? []);

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
        $osName = $this->osReleaseCatalogName($meldung['os_id'] ?? null, $meldung['os_version'] ?? null);
        $dienste = $this->imKatalog(collect($meldung['services'] ?? [])
            ->map(fn ($unit) => self::LINUX_DIENSTE[$unit] ?? null)
            ->filter()
            ->all());

        if ($meldung['virtual'] ?? false) {
            $kurz = mb_strtolower(explode('.', $meldung['hostname'])[0]);
            $geraet = VM::where('customer_id', $customer->id)->where('site_id', $site->id)->where('agent_identifier', $meldung['identifier'])->first()
                ?? VM::where('customer_id', $customer->id)->where('site_id', $site->id)
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

            if ($osId = $this->catalogOsId($osName)) {
                $geraet->operating_system_id = $osId;
            }
            $geraet->save();
            $this->reportUrl($geraet, $customer->id, $site->id, $meldung['ip'] ?? null);
        } else {
            [$geraet] = $this->hostUndGaeste($meldung, [], $customer, $site, (string) $osName, fromOsRelease: true);
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
     * Übersetzt gemeldete Windows-Rollen in Dienste aus dem Katalog.
     *
     * Der Abgleich gegen die vorhandenen Dienste ist Absicht: der Agent legt
     * keinen Katalogeintrag an. Sonst stünden nach dem ersten Lauf Dienste in
     * der Auswahl, die niemand angelegt hat - und bei jedem Kunden andere.
     *
     * @param  array<int, string>  $rollen
     * @return array<int, string>
     */
    protected function servicesFromRoles(array $rollen): array
    {
        $gewuenscht = collect($rollen)
            ->map(fn ($rolle) => self::WINDOWS_ROLLEN[$rolle] ?? null)
            ->filter();

        if ($gewuenscht->isEmpty()) {
            return [];
        }

        return $this->imKatalog($gewuenscht->all());
    }

    /**
     * The catalog names for wanted services: case-insensitive ("docker"
     * finds "Docker"), in the catalog's spelling. An entry may list
     * alternatives ("FS" or "Fileserver") - the first one the catalog has
     * counts. What the catalog does not have is dropped: agents never create
     * catalog entries.
     *
     * @param  array<int, string|array<int, string>>  $gewuenscht
     * @return array<int, string>
     */
    protected function imKatalog(array $gewuenscht): array
    {
        $katalog = Service::pluck('name')->keyBy(fn ($name) => mb_strtolower($name));

        return collect($gewuenscht)
            ->map(fn ($eintrag) => collect((array) $eintrag)
                ->map(fn ($name) => $katalog[mb_strtolower($name)] ?? null)
                ->first(fn ($name) => $name !== null))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
