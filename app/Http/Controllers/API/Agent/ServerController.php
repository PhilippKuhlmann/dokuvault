<?php

namespace App\Http\Controllers\API\Agent;

use App\Http\Controllers\API\Agent\Concerns\ReportsMachines;
use App\Models\ADDomainHost;
use App\Models\Computer;
use App\Models\CredentialLink;
use App\Models\NetworkShare;
use App\Models\Server;
use App\Models\Service;
use App\Models\VM;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'server.smbios_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        // Running in a VM a hypervisor agent already documents: the report
        // belongs on that VM, not on a server of its own.
        if ($vm = $this->vmWithBiosUuid($customer->id, $data['server']['smbios_uuid'] ?? null)) {
            return $this->windowsGuest($vm, $data['server'], $customer);
        }

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
     * The VM a hypervisor agent reported with this BIOS UUID.
     *
     * Customer-wide: the VM's site comes from its host, not from the token
     * of the agent inside. Both spellings of the UUID are tried - SMBIOS
     * stores the first three fields little-endian, and not every reader
     * turns them round the same way.
     */
    protected function vmWithBiosUuid(int $customerId, ?string $uuid): ?VM
    {
        if (! $uuid = $this->smbiosUuid($uuid)) {
            return null;
        }

        return VM::where('customer_id', $customerId)
            ->whereIn('smbios_uuid', [$uuid, $this->swappedUuid($uuid)])
            ->orderBy('id')
            ->first();
    }

    /** "aabbccdd-eeff-gghh-..." -> "ddccbbaa-ffee-hhgg-...". */
    protected function swappedUuid(string $uuid): string
    {
        $teile = explode('-', $uuid);
        foreach ([0, 1, 2] as $i) {
            $teile[$i] = implode('', array_reverse(str_split($teile[$i], 2)));
        }

        return implode('-', $teile);
    }

    /**
     * What Windows says about itself, put on the VM it runs in.
     *
     * The VM keeps its name and its figures from the hypervisor; Windows
     * adds its own host name (the AD agent finds domain controllers by it),
     * the operating system, its address and - into an empty field - the
     * services from its roles. A server an earlier run created for the same
     * machine is folded into the VM.
     */
    protected function windowsGuest(VM $vm, array $meldung, $customer)
    {
        $vm->hostname = $meldung['hostname'];

        if ($osId = $this->catalogOsId($this->osCatalogName($meldung['os'] ?? null, 'Windows'))) {
            $vm->operating_system_id = $osId;
        }
        $vm->save();

        $uebernommen = Server::where('customer_id', $customer->id)
            ->where('agent_identifier', $meldung['identifier'])
            ->get()
            ->filter(fn (Server $server) => $this->foldIntoVm($server, $vm))
            ->count();

        $this->reportUrl($vm, $customer->id, $vm->site_id, $meldung['ip'] ?? null);

        $dienste = $this->servicesFromRoles($meldung['roles'] ?? []);
        if ($dienste !== [] && blank($vm->fresh()->getRawOriginal('services'))) {
            $vm->update(['services' => implode(',', $dienste)]);
        }

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'type' => 'vm',
            'vm' => $vm->name,
            'vm_id' => $vm->id,
            'servers_folded' => $uebernommen,
            'services_documented' => count($dienste),
        ]);
    }

    /**
     * A server the Windows agent created before the VM was known moves into
     * the VM: addresses, linked logins, AD roles, network shares, remote
     * access and services go over, the server goes to the trash
     * (restorable there).
     *
     * Not when it carries what a VM cannot: a rack slot or guests of its
     * own - then it stays for a human to sort out.
     */
    protected function foldIntoVm(Server $server, VM $vm): bool
    {
        if ($server->rackItem || $server->gaeste()->exists()) {
            return false;
        }

        DB::transaction(function () use ($server, $vm) {
            $vorhanden = $vm->ipAddresses()->pluck('address')->filter()->all();
            foreach ($server->ipAddresses as $adresse) {
                if ($adresse->address !== null && in_array($adresse->address, $vorhanden, true)) {
                    $adresse->delete();
                } else {
                    $adresse->ipable()->associate($vm)->save();
                }
            }

            foreach (CredentialLink::where('credentialable_type', $server->getMorphClass())->where('credentialable_id', $server->id)->get() as $link) {
                $doppelt = CredentialLink::where('credentialable_type', $vm->getMorphClass())->where('credentialable_id', $vm->id)
                    ->where('login_general_id', $link->login_general_id)->exists();
                $doppelt ? $link->delete() : $link->update(['credentialable_type' => $vm->getMorphClass(), 'credentialable_id' => $vm->id]);
            }

            foreach (ADDomainHost::where('host_type', $server->getMorphClass())->where('host_id', $server->id)->get() as $rolle) {
                $doppelt = ADDomainHost::where('host_type', $vm->getMorphClass())->where('host_id', $vm->id)
                    ->where('ad_domain_id', $rolle->ad_domain_id)->where('role', $rolle->role)->exists();
                $doppelt ? $rolle->delete() : $rolle->update(['host_type' => $vm->getMorphClass(), 'host_id' => $vm->id]);
            }

            NetworkShare::withTrashed()->where('host_type', $server->getMorphClass())->where('host_id', $server->id)
                ->update(['host_type' => $vm->getMorphClass(), 'host_id' => $vm->id]);

            // Raw values: the remote password is stored encrypted on both.
            $vonServer = array_filter([
                'services' => blank($vm->getRawOriginal('services')) ? $server->getRawOriginal('services') : null,
                'remoteID' => blank($vm->getRawOriginal('remoteID')) ? $server->getRawOriginal('remoteID') : null,
                'remotePassword' => blank($vm->getRawOriginal('remotePassword')) ? $server->getRawOriginal('remotePassword') : null,
            ], fn ($wert) => filled($wert));
            if ($vonServer) {
                DB::table('vms')->where('id', $vm->id)->update($vonServer);
            }

            $server->delete();
        });

        return true;
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
