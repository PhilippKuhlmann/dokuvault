<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Accesspoint;
use App\Models\ADGroup;
use App\Models\ADUser;
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
use Illuminate\Http\Request;

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
        ]));

        // Versionsspezifisch ("Proxmox VE 8" statt nur "Proxmox VE"): Version
        // 7/8/9 haben unterschiedliche Support-Enden, ein Sammel-Eintrag
        // haette das nicht abbilden koennen.
        [$server, $guestCount] = $this->hostUndGaeste(
            $data['host'], $data['guests'] ?? [], $customer, $site,
            $this->mapPveVersion($data['host']['pve_version'] ?? null)
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

        $os = OperatingSystem::firstOrCreate(['name' => $this->osKatalogName($client['os'] ?? null, 'Windows')]);

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
    protected function hostUndGaeste(array $host, array $gaeste, $customer, $site, string $hostOs): array
    {
        $os = OperatingSystem::firstOrCreate(['name' => $hostOs]);

        // Hinweis: 'services' wird hier NICHT gesetzt - das Feld pflegt der
        // Nutzer manuell (Rollen wie AD, FS, DNS, DHCP ...). Allein
        // windowsServer() traegt etwas ein, und auch nur in ein leeres Feld.
        $attribute = [
            'site_id' => $site->id,
            'operating_system_id' => $os->id,
            'name' => $host['hostname'],
        ];

        // Nur gemeldete Felder schreiben. vCenter gibt Hersteller, Modell und
        // Seriennummer nicht heraus - wuerde hier stur null eingetragen, loeschte
        // jeder Lauf, was jemand von Hand nachgetragen hat.
        foreach (['manufacturer' => 'manufacturer', 'model' => 'model', 'serial' => 'serialNumber'] as $quelle => $spalte) {
            if (array_key_exists($quelle, $host)) {
                $attribute[$spalte] = $host[$quelle];
            }
        }

        $server = Server::updateOrCreate(
            ['customer_id' => $customer->id, 'agent_identifier' => $host['identifier']],
            $attribute
        );

        // Die IP ist keine Spalte am Geraet mehr, sondern ein Eintrag im Block
        // "Weitere IP-Adressen".
        $this->meldeAdresse($server, $customer->id, $site->id, $host['ip'] ?? null);

        $anzahl = 0;
        foreach ($gaeste as $gast) {
            // Proxmox meldet eine Kennung ('l26', 'win11'), die anderen einen
            // lesbaren Namen ("Windows Server 2022"). Ein lesbarer Name sticht.
            $gastOs = OperatingSystem::firstOrCreate([
                'name' => array_key_exists('os', $gast) && filled($gast['os'])
                    ? $this->osKatalogName($gast['os'], 'Unbekannt')
                    : $this->mapOstype($gast['ostype'] ?? null),
            ]);

            $vm = VM::updateOrCreate(
                ['customer_id' => $customer->id, 'agent_identifier' => $gast['identifier']],
                [
                    'site_id' => $site->id,
                    'server_id' => $server->id,
                    'operating_system_id' => $gastOs->id,
                    'name' => $gast['name'] ?? ('VM '.($gast['vmid'] ?? '')),
                    // 'services' bleibt manuell (Rollen der VM)
                ]
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
                // 'username'/'password' bleiben unangetastet (manuell gepflegt)
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
            'guests.*.ostype' => ['nullable', 'string', 'max:64'],
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

    /**
     * "8.2.4" -> "Proxmox VE 8". Ohne auswertbare Hauptversion (Script zu alt,
     * pveversion nicht verfuegbar) faellt es auf den unversionierten
     * Sammel-Eintrag zurueck statt einen falschen Wert zu raten.
     */
    protected function mapPveVersion(?string $pveVersion): string
    {
        if ($pveVersion && preg_match('/^(\d+)/', $pveVersion, $treffer)) {
            return 'Proxmox VE '.$treffer[1];
        }

        return 'Proxmox VE';
    }
}
