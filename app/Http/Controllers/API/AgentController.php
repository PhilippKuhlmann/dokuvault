<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\Computer;
use App\Models\OperatingSystem;
use App\Models\Server;
use App\Models\VM;
use Illuminate\Http\Request;

class AgentController extends Controller
{
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

        $data = $request->validate([
            'host.identifier' => ['required', 'string', 'max:255'],
            'host.hostname' => ['required', 'string', 'max:255'],
            'host.manufacturer' => ['nullable', 'string', 'max:255'],
            'host.model' => ['nullable', 'string', 'max:255'],
            'host.serial' => ['nullable', 'string', 'max:255'],
            'host.ip' => ['nullable', 'string', 'max:255'],
            'host.pve_version' => ['nullable', 'string', 'max:255'],
            'host.kernel' => ['nullable', 'string', 'max:255'],
            'host.cpu' => ['nullable', 'string', 'max:255'],
            'host.memory_gb' => ['nullable', 'numeric'],
            'host.storages' => ['nullable', 'array'],
            'host.storages.*.name' => ['nullable', 'string', 'max:255'],
            'host.storages.*.type' => ['nullable', 'string', 'max:255'],
            'host.storages.*.total_gb' => ['nullable', 'numeric'],
            'host.storages.*.used_gb' => ['nullable', 'numeric'],
            'guests' => ['nullable', 'array'],
            'guests.*.identifier' => ['required_with:guests', 'string', 'max:255'],
            'guests.*.name' => ['nullable', 'string', 'max:255'],
            'guests.*.vmid' => ['nullable', 'integer'],
            'guests.*.type' => ['nullable', 'string', 'max:32'],
            'guests.*.ostype' => ['nullable', 'string', 'max:64'],
            'guests.*.ip' => ['nullable', 'string', 'max:255'],
            'guests.*.status' => ['nullable', 'string', 'max:32'],
            'guests.*.cores' => ['nullable', 'integer'],
            'guests.*.memory_gb' => ['nullable', 'numeric'],
        ]);

        $host = $data['host'];

        // Betriebssystem bewusst versionslos ("Proxmox VE").
        $os = OperatingSystem::firstOrCreate(['name' => 'Proxmox VE']);

        // Hinweis: 'services' wird NICHT gesetzt – das Feld pflegt der Nutzer
        // manuell (Rollen wie AD, FS, DNS, DHCP …). updateOrCreate lässt
        // nicht angegebene Spalten unverändert.
        $server = Server::updateOrCreate(
            ['customer_id' => $customer->id, 'agent_identifier' => $host['identifier']],
            [
                'site_id' => $site->id,
                'operating_system_id' => $os->id,
                'name' => $host['hostname'],
                'manufacturer' => $host['manufacturer'] ?? null,
                'model' => $host['model'] ?? null,
                'serialNumber' => $host['serial'] ?? null,
            ]
        );

        // Die IP ist keine Spalte am Geraet mehr, sondern ein Eintrag im Block
        // "Weitere IP-Adressen".
        $this->meldeAdresse($server, $customer->id, $host['ip'] ?? null);

        $guestCount = 0;
        foreach ($data['guests'] ?? [] as $guest) {
            $guestOs = OperatingSystem::firstOrCreate(['name' => $this->mapOstype($guest['ostype'] ?? null)]);

            $vm = VM::updateOrCreate(
                ['customer_id' => $customer->id, 'agent_identifier' => $guest['identifier']],
                [
                    'site_id' => $site->id,
                    'server_id' => $server->id,
                    'operating_system_id' => $guestOs->id,
                    'name' => $guest['name'] ?? ('VM '.($guest['vmid'] ?? '')),
                    // 'services' bleibt manuell (Rollen der VM)
                ]
            );

            $this->meldeAdresse($vm, $customer->id, $guest['ip'] ?? null);
            $guestCount++;
        }

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
        ]);

        $userCount = 0;
        foreach ($data['users'] ?? [] as $u) {
            ADUser::updateOrCreate(
                ['customer_id' => $customer->id, 'agent_identifier' => $u['identifier']],
                [
                    'firstName' => $u['firstName'] ?? null,
                    'lastName' => $u['lastName'] ?? null,
                    'username' => $u['username'] ?? null,
                    'email' => $u['email'] ?? null,
                    'enabled' => array_key_exists('enabled', $u) ? (bool) $u['enabled'] : null,
                    // 'password' bleibt bewusst unangetastet (manuell gepflegt)
                ]
            );
            $userCount++;
        }

        $groupCount = 0;
        foreach ($data['groups'] ?? [] as $g) {
            ADGroup::updateOrCreate(
                ['customer_id' => $customer->id, 'agent_identifier' => $g['identifier']],
                [
                    'name' => $g['name'] ?? null,
                    'description' => $g['description'] ?? null,
                ]
            );
            $groupCount++;
        }

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'domain' => $data['domain'] ?? null,
            'users_documented' => $userCount,
            'groups_documented' => $groupCount,
        ]);
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

        // Win32_OperatingSystem.Caption liefert immer "Microsoft Windows ...";
        // der Katalog fuehrt Windows-Systeme ohne dieses Praefix (siehe Seeder,
        // z. B. "Windows Server 2012 R2 Standard"). Ohne das Kappen legte
        // firstOrCreate bei jedem Kunden eine zweite, nie zusammengefuehrte
        // Katalogzeile an statt die vorhandene "Windows 11 Pro" zu treffen.
        $osName = trim(preg_replace('/^Microsoft\s+/i', '', $client['os'] ?? 'Windows'));
        $os = OperatingSystem::firstOrCreate(['name' => $osName !== '' ? $osName : 'Windows']);

        $computer = Computer::updateOrCreate(
            ['customer_id' => $customer->id, 'agent_identifier' => $client['identifier']],
            [
                'site_id' => $site->id,
                'operating_system_id' => $os->id,
                'name' => $client['hostname'],
                'manufacturer' => $client['manufacturer'] ?? null,
                'model' => $client['model'] ?? null,
                'serialNumber' => $client['serial'] ?? null,
            ]
        );

        $this->meldeAdresse($computer, $customer->id, $client['ip'] ?? null);

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'site' => $site->name,
            'client' => $computer->name,
            'client_id' => $computer->id,
        ]);
    }

    /**
     * Traegt die gemeldete Adresse im Block "Weitere IP-Adressen" ein.
     *
     * updateOrCreate statt create: Der Agent meldet denselben Host wieder und
     * wieder - sonst stuenden dort nach einer Woche sieben gleiche Zeilen.
     * Gepflegte Angaben (Netz, Bezeichnung) bleiben dabei unangetastet.
     */
    protected function meldeAdresse($geraet, int $customerId, ?string $adresse): void
    {
        $adresse = trim((string) $adresse);

        if ($adresse === '') {
            return;
        }

        $geraet->ipAddresses()->updateOrCreate(
            ['address' => $adresse],
            ['customer_id' => $customerId]
        );
    }

    protected function mapOstype(?string $ostype): string
    {
        if (! $ostype) {
            return 'Unbekannt';
        }

        return match (true) {
            str_starts_with($ostype, 'l2') => 'Linux',
            str_starts_with($ostype, 'win') => 'Windows',
            $ostype === 'solaris' => 'Solaris',
            default => ucfirst($ostype),
        };
    }
}
