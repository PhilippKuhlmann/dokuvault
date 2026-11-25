<?php

namespace Database\Seeders;

use App\Models\Accesspoint;
use App\Models\ADDomain;
use App\Models\ADDomainHost;
use App\Models\ADGroup;
use App\Models\ADUser;
use App\Models\Backup;
use App\Models\Camera;
use App\Models\Certificate;
use App\Models\ChecklistOption;
use App\Models\Cluster;
use App\Models\Computer;
use App\Models\ContactPerson;
use App\Models\Customer;
use App\Models\DECT;
use App\Models\Domain;
use App\Models\DynDNS;
use App\Models\File;
use App\Models\Firewall;
use App\Models\FTPServer;
use App\Models\InternetConnection;
use App\Models\IoTDevice;
use App\Models\IpRange;
use App\Models\LicenseAccess;
use App\Models\LicenseSoftware;
use App\Models\LicenseWindows;
use App\Models\LoginGeneral;
use App\Models\LoginWebsite;
use App\Models\Machine;
use App\Models\Mailbox;
use App\Models\MailboxProvider;
use App\Models\NAS;
use App\Models\Network;
use App\Models\NetworkShare;
use App\Models\NetworkSwitch;
use App\Models\OperatingSystem;
use App\Models\OtherClient;
use App\Models\PatchPanel;
use App\Models\Phone;
use App\Models\PhoneSystem;
use App\Models\Printer;
use App\Models\Rack;
use App\Models\RackCatalogItem;
use App\Models\Recorder;
use App\Models\Role;
use App\Models\Router;
use App\Models\Scanner;
use App\Models\ScanTarget;
use App\Models\SecurepointUMA;
use App\Models\Server;
use App\Models\SipAccount;
use App\Models\Site;
use App\Models\SshKey;
use App\Models\Ups;
use App\Models\User;
use App\Models\VM;
use App\Models\Wifi;
use App\Support\ChecklistBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The demo customer: Mustermann, a metalworking company - laser cutting,
 * bending, CNC milling - with its head office and production in Hamburg and
 * a small sales office in Munich. About 25 people.
 *
 * Fixed values, no dice: a visitor of the demo looks at it as at a real
 * company, and every random value that contradicts another (a domain
 * controller running "SQL, Backup", two devices on one address, a branch in
 * the wrong town) makes the whole thing look made up.
 *
 * What it shows on purpose:
 * - every module filled - nothing a visitor opens is empty
 * - data from agents next to data entered by hand (Proxmox, Hyper-V, the
 *   Windows agent, UniFi, the firewall, Microsoft 365, the network scan)
 * - an IT in transition: the old Hyper-V host with its Windows VMs is being
 *   replaced by a three-node Proxmox cluster with Ceph
 * - a few things that need attention: a failed backup, a certificate and a
 *   licence about to expire, a server out of warranty soon, an operating
 *   system past its support, a disabled user, an undocumented switch
 *
 * The people: the AD users are the staff, and the mailboxes, licences,
 * computers and phone extensions belong to the same people.
 *
 * Addresses: 10.10.x.0/24 in Hamburg (x = VLAN), 10.20.x.0/24 in Munich.
 * The domain controllers are the DNS servers, the firewall is the gateway.
 */
class DemoCompanySeeder extends Seeder
{
    private Customer $customer;

    private Site $hamburg;

    private Site $munich;

    private ?ADDomain $domain = null;

    /** @var array<string, Network> by short name */
    private array $vlan = [];

    public function run(): void
    {
        $this->customer = Customer::create(['name' => 'Mustermann', 'customer_number' => '10042']);

        $this->hamburg = $this->make(Site::class, [
            'name' => 'Zentrale Hamburg', 'street' => 'Werkstraße', 'house_number' => '14',
            'zip' => '21107', 'city' => 'Hamburg',
        ]);
        $this->munich = $this->make(Site::class, [
            'name' => 'Filiale München', 'street' => 'Gewerbering', 'house_number' => '7',
            'zip' => '81249', 'city' => 'München',
        ]);

        $this->customerLogins();
        $this->contacts();
        $this->networks();
        $this->internet();
        $people = $this->activeDirectory();
        $this->firewallAndRouters();
        $this->servers();
        $this->storage();
        $this->switchesAndWifi();
        $this->clients($people);
        $this->printing();
        $this->telephony($people);
        $this->security();
        $this->production();
        $this->racks();
        $this->logins();
        $this->licences();
        $this->mail();
        $this->domainsAndCertificates();
        $this->servicesOutside();
        $this->backups();
        $this->shares();
        $this->checklists();
        $this->files();
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    /** Creates a row of the customer - fixed values, no factory. */
    private function make(string $class, array $values): Model
    {
        return $class::forceCreate(['customer_id' => $this->customer->id, ...$values]);
    }

    /** An address in the IP plan; with the network the map ties it to its VLAN. */
    private function ip(Model $device, ?string $address, ?string $vlan = null, string $label = 'Primär', bool $dhcp = false): void
    {
        $device->ipAddresses()->create([
            'customer_id' => $this->customer->id,
            'network_id' => $vlan ? $this->vlan[$vlan]->id : null,
            'address' => $address,
            'label' => $label,
            'dhcp' => $dhcp,
        ]);
    }

    /**
     * A serial number that looks like one and stays the same on every reset:
     * derived from a seed instead of drawn.
     */
    private function serial(string $seed, int $length = 10): string
    {
        $alphabet = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $out = '';
        $hash = md5($seed);
        for ($i = 0; strlen($out) < $length; $i++) {
            if ($i > 0 && $i % 15 === 0) {
                $hash = md5($hash);
            }
            $out .= $alphabet[hexdec(substr($hash, ($i % 15) * 2, 2)) % strlen($alphabet)];
        }

        return $out;
    }

    /** A GUID as AD, Hyper-V or Microsoft 365 report them - fixed per seed. */
    private function guid(string $seed): string
    {
        $h = md5($seed);

        return substr($h, 0, 8).'-'.substr($h, 8, 4).'-'.substr($h, 12, 4).'-'.substr($h, 16, 4).'-'.substr($h, 20, 12);
    }

    private function mac(string $prefix, string $seed): string
    {
        $h = md5($seed);

        return strtoupper($prefix.':'.substr($h, 0, 2).':'.substr($h, 2, 2).':'.substr($h, 4, 2));
    }

    /** A remote-access ID like the tools show it: three groups of three. */
    private function remoteId(string $seed): string
    {
        return implode(' ', str_split(substr(sprintf('%09d', crc32($seed)), 0, 9), 3));
    }

    private function os(string $name): ?int
    {
        return OperatingSystem::where('name', $name)->value('id');
    }

    private function device(string $class, string $name): ?Model
    {
        return $class::where('customer_id', $this->customer->id)->where('name', $name)->first();
    }

    private function link(?Model $device, LoginGeneral $login, ?string $note = null): void
    {
        $device?->credentialLinks()->create([
            'customer_id' => $this->customer->id,
            'login_general_id' => $login->id,
            'note' => $note,
        ]);
    }

    private function site(string $key): int
    {
        return $key === 'muc' ? $this->munich->id : $this->hamburg->id;
    }

    /* ------------------------------------------------------------------ */
    /* The company */
    /* ------------------------------------------------------------------ */

    /** Two logins of the customer itself, to try the customer view of the demo. */
    private function customerLogins(): void
    {
        foreach ([['Kunde Lesen/Schreiben', 'kunde-rw', 'general_full'], ['Kunde nur Lesen', 'kunde-r', 'general_read']] as [$name, $username, $role]) {
            User::factory()->create([
                'name' => $name, 'username' => $username, 'password' => bcrypt('password'),
                'role_id' => Role::where('name', $role)->firstOrFail()->id, 'customer_id' => $this->customer->id,
            ]);
        }
    }

    private function contacts(): void
    {
        foreach ([
            ['Max', 'Mustermann', 'Geschäftsführer', '040 7541 3210', 'max.mustermann@mustermann.de'],
            ['Erika', 'Mustermann', 'Prokuristin, Buchhaltung', '040 7541 3211', 'erika.mustermann@mustermann.de'],
            ['Michael', 'Krüger', 'Fertigungsleitung, IT-Ansprechpartner vor Ort', '040 7541 3240', 'michael.krueger@mustermann.de'],
            ['Stefan', 'Braun', 'Leitung Filiale München', '089 8973 1260', 'stefan.braun@mustermann.de'],
        ] as [$first, $last, $role, $phone, $mail]) {
            $this->make(ContactPerson::class, ['first_name' => $first, 'last_name' => $last, 'role' => $role, 'phone' => $phone, 'mail' => $mail]);
        }
    }

    /**
     * The VLANs. DNS: the two domain controllers everywhere but in the guest
     * Wi-Fi, which asks the internet. One mistake left on purpose: Clients
     * has Google as second DNS - the activity log shows a technician fixing
     * it (DemoProtokollSeeder).
     */
    private function networks(): void
    {
        $dns = ['10.10.30.10', '10.10.30.20'];

        foreach ([
            // [key, site, description, vlan, network, dhcp from, dhcp to, dns]
            ['clients', 'hh', 'Clients', 20, '10.10.20.0', 100, 200, [$dns[0], '8.8.8.8']],
            ['server', 'hh', 'Server & Management', 30, '10.10.30.0', null, null, $dns],
            ['voip', 'hh', 'VoIP', 40, '10.10.40.0', 100, 199, $dns],
            ['guest', 'hh', 'WLAN-Gast', 50, '10.10.50.0', 20, 250, ['9.9.9.9', '1.1.1.1']],
            ['printers', 'hh', 'Drucker', 60, '10.10.60.0', null, null, $dns],
            ['cameras', 'hh', 'Kameras', 70, '10.10.70.0', null, null, $dns],
            ['backup', 'hh', 'Backup', 80, '10.10.80.0', null, null, $dns],
            ['production', 'hh', 'Produktion', 90, '10.10.90.0', 100, 150, $dns],
            ['building', 'hh', 'Gebäudetechnik', 100, '10.10.100.0', null, null, $dns],
            ['dmz', 'hh', 'DMZ', 250, '172.16.10.0', null, null, $dns],
            ['muc-clients', 'muc', 'Clients', 20, '10.20.20.0', 100, 200, $dns],
            ['muc-voip', 'muc', 'VoIP', 40, '10.20.40.0', 100, 150, $dns],
            ['muc-guest', 'muc', 'WLAN-Gast', 50, '10.20.50.0', 20, 120, ['9.9.9.9', '1.1.1.1']],
        ] as [$key, $site, $description, $vlanId, $network, $from, $to, [$dns1, $dns2]]) {
            $prefix = substr($network, 0, strrpos($network, '.'));
            $this->vlan[$key] = $this->make(Network::class, [
                'site_id' => $this->site($site),
                'description' => $description, 'vlanId' => $vlanId, 'network' => $network,
                'subnetmask' => '255.255.255.0', 'cidr' => '24', 'gateway' => $prefix.'.1',
                'dns1' => $dns1, 'dns2' => $dns2,
                'dhcpStart' => $from ? $prefix.'.'.$from : null, 'dhcpEnd' => $to ? $prefix.'.'.$to : null,
            ]);
        }

        // The routed /28 of the fibre line: between the Telekom router and
        // the firewall, without a VLAN.
        $this->vlan['wan'] = $this->make(Network::class, [
            'site_id' => $this->hamburg->id, 'description' => 'WAN Telekom', 'network' => '203.0.113.16',
            'subnetmask' => '255.255.255.240', 'cidr' => '28', 'gateway' => '203.0.113.17',
        ]);

        // Reserved, adjacent blocks in Clients - each in its own colour in
        // the IP plan. The second holds the fixed CAD workstations, the
        // third the reception, where PC-EMPFANG sits inside it.
        foreach ([
            ['10.10.20.10', '10.10.20.19', 'Infrastruktur (reserviert)'],
            ['10.10.20.20', '10.10.20.39', 'Feste Arbeitsplätze Konstruktion'],
            ['10.10.20.40', '10.10.20.59', 'Empfang und Besprechung'],
        ] as [$from, $to, $label]) {
            IpRange::create(['customer_id' => $this->customer->id, 'network_id' => $this->vlan['clients']->id, 'from_ip' => $from, 'to_ip' => $to, 'label' => $label]);
        }
    }

    private function internet(): void
    {
        $this->make(InternetConnection::class, [
            'site_id' => $this->hamburg->id, 'provider' => 'Deutsche Telekom', 'product' => 'DeutschlandLAN Connect IP 1000',
            'contract_number' => 'DTAG-7300418852', 'connection_type' => 'Glasfaser', 'bandwidth_down' => '1000', 'bandwidth_up' => '500',
            'wan_ip' => '203.0.113.18', 'subnet' => '203.0.113.16/28', 'subnet_gateway' => '203.0.113.17',
            'hotline' => '0800 330 4711', 'notes' => 'Feste IPv4 /28, Störungsannahme rund um die Uhr. Vertrag läuft bis 03/2028.',
        ]);
        $this->make(InternetConnection::class, [
            'site_id' => $this->munich->id, 'provider' => 'Vodafone', 'product' => 'Business Kabel 500',
            'contract_number' => 'VF-1189034417', 'connection_type' => 'Kabel', 'bandwidth_down' => '500', 'bandwidth_up' => '50',
            'wan_ip' => '198.51.100.24', 'hotline' => '0800 172 4711', 'notes' => 'Feste IP, Site-to-Site-VPN zur Zentrale.',
        ]);
    }

    /**
     * The staff and their directory. Most of it the AD agent on SRV-DC01
     * reports (agent_identifier = objectGUID); one trainee was entered by
     * hand and his status never documented - the third state of the list.
     *
     * @return array<string, array{0: ?string, 1: ?string, 2: ?string, 3: ?int, 4: ADUser, 5: string}> by username
     */
    private function activeDirectory(): array
    {
        $this->domain = $this->make(ADDomain::class, [
            'domain' => 'ad.mustermann.de', 'netbios' => 'MUSTERMANN', 'functional_level' => '2016',
            'fsmo_holder' => 'SRV-DC01', 'upn_suffixes' => 'mustermann.de', 'dns_forwarders' => '9.9.9.9, 149.112.112.112',
            'dhcp_server' => 'SRV-DC01 (Failover mit SRV-DC02)', 'entra_connect' => true,
            'dsrmpassword' => 'Dsrm!Werk-2019', 'notes' => 'SRV-DC01 wird mit dem Hyper-V-Host abgelöst; die FSMO-Rollen ziehen dann auf SRV-DC02.',
        ]);

        // [first, last, department, extension, site, enabled, by agent]
        $staff = [
            ['Max', 'Mustermann', 'Geschäftsführung', 10, 'hh', true, true],
            ['Erika', 'Mustermann', 'Buchhaltung', 11, 'hh', true, true],
            ['Petra', 'Schulz', 'Empfang', 12, 'hh', true, true],
            ['Anna', 'Richter', 'Buchhaltung', 13, 'hh', true, true],
            ['Sabine', 'Roth', 'Einkauf', 15, 'hh', true, true],
            ['Jonas', 'Becker', 'Vertrieb', 20, 'hh', true, true],
            ['Laura', 'Schneider', 'Vertrieb', 21, 'hh', true, true],
            ['Tobias', 'Wagner', 'Konstruktion', 30, 'hh', true, true],
            ['Sandra', 'Hoffmann', 'Konstruktion', 31, 'hh', true, true],
            ['Michael', 'Krüger', 'Fertigung', 40, 'hh', true, true],
            ['Dennis', 'Wolf', 'Lager', 50, 'hh', true, true],
            ['Stefan', 'Braun', 'Vertrieb', 60, 'muc', true, true],
            ['Julia', 'Neumann', 'Vertrieb', 61, 'muc', true, true],
            // Left the company: disabled, mailbox kept for a while.
            ['Frank', 'Zimmermann', 'Vertrieb', null, 'hh', false, true],
            // Trainee, entered by hand - status never documented.
            ['Kevin', 'Hartmann', 'Fertigung', null, 'hh', null, false],
        ];

        $people = [];
        foreach ($staff as [$first, $last, $department, $extension, $site, $enabled, $byAgent]) {
            $username = ChecklistBuilder::username($first, $last);
            $user = $this->make(ADUser::class, [
                'firstName' => $first, 'lastName' => $last, 'username' => $username,
                'email' => $username.'@mustermann.de', 'enabled' => $enabled,
                'agent_identifier' => $byAgent ? $this->guid('ad:'.$username) : null,
            ]);
            $people[$username] = [$first, $last, $department, $extension, $user, $site];
        }

        // Accounts without a person: no address, no name.
        foreach (['administrator', 'svc-backup', 'svc-scan'] as $account) {
            $people[$account] = [null, null, null, null, $this->make(ADUser::class, [
                'username' => $account, 'enabled' => true, 'agent_identifier' => $this->guid('ad:'.$account),
            ]), 'hh'];
        }

        $in = fn (string ...$departments) => collect($people)
            ->filter(fn ($p) => in_array($p[2], $departments, true) && $p[4]->enabled !== false)
            ->map(fn ($p) => $p[4]);
        $named = fn (string ...$usernames) => collect($usernames)->map(fn ($u) => $people[$u][4]);
        $everyone = collect($people)->filter(fn ($p) => $p[0] !== null && $p[4]->enabled !== false)->map(fn ($p) => $p[4]);

        foreach ([
            ['Domänen-Admins', 'Vordefinierte Gruppe: Administratoren der Domäne', $named('administrator')],
            ['Backup-Operatoren', 'Vordefinierte Gruppe: dürfen sichern und wiederherstellen', $named('svc-backup')],
            ['GG_Geschäftsführung', 'Globale Gruppe Geschäftsführung', $in('Geschäftsführung')->merge($named('erika.mustermann'))],
            ['GG_Buchhaltung', 'Globale Gruppe Buchhaltung', $in('Buchhaltung')],
            ['GG_Einkauf', 'Globale Gruppe Einkauf', $in('Einkauf')],
            ['GG_Vertrieb', 'Globale Gruppe Vertrieb', $in('Vertrieb')],
            ['GG_Konstruktion', 'Globale Gruppe Konstruktion (CAD)', $in('Konstruktion')],
            ['GG_Fertigung', 'Globale Gruppe Fertigung und Lager', $in('Fertigung', 'Lager')],
            ['GG_Filiale_München', 'Alle Mitarbeiter der Filiale München', $named('stefan.braun', 'julia.neumann')],
            ['VPN-Benutzer', 'Zugang über das SSL-VPN der Securepoint', $named('max.mustermann', 'jonas.becker', 'laura.schneider', 'tobias.wagner')],
            ['RDS-Benutzer', 'Anmeldung am Terminalserver SRV-RDS01', $in('Fertigung', 'Lager')],
            ['M365-Standard', 'Lizenzgruppe Microsoft 365 Business Premium', $everyone],
        ] as [$name, $description, $members]) {
            $this->make(ADGroup::class, ['name' => $name, 'description' => $description, 'agent_identifier' => $this->guid('adg:'.$name)])
                ->users()->attach($members->pluck('id')->unique()->values());
        }

        return $people;
    }

    /**
     * Hamburg: the Telekom router holds the /28, the Securepoint behind it
     * is the gateway of every VLAN - and reports itself through the agent.
     * Munich: one Lancom does everything, documented by hand.
     */
    private function firewallAndRouters(): void
    {
        $telekom = $this->make(Router::class, [
            'site_id' => $this->hamburg->id, 'name' => 'RTR-HH-Telekom', 'manufacturer' => 'Telekom', 'model' => 'Digitalisierungsbox Premium 2',
            'serialNumber' => $this->serial('rtr-hh'), 'port' => 443, 'supplier' => 'Deutsche Telekom (Miete)',
        ]);
        $this->ip($telekom, '203.0.113.17', 'wan', 'Gateway /28');

        $vlanInterfaces = collect(['clients', 'server', 'voip', 'guest', 'printers', 'cameras', 'backup', 'production', 'building', 'dmz']);
        $serial = 'SP'.$this->serial('fw-hh', 10);

        $firewall = $this->make(Firewall::class, [
            'site_id' => $this->hamburg->id, 'name' => 'FW-HH', 'manufacturer' => 'Securepoint', 'model' => 'RC300 G5',
            'serialNumber' => $serial, 'firmware' => '14.1.9.3', 'form_factor' => 'appliance',
            'height_units' => 1, 'full_depth' => false,
            'management_url' => 'https://10.10.30.1:11115', 'port' => 11115,
            'url_user' => 'https://10.10.30.1', 'url_external' => 'https://vpn.mustermann.de',
            'usc_pin' => '448213', 'cloud_backup_password' => 'Wolke!2026sicher',
            'subscription_until' => now()->addMonths(5)->toDateString(),
            'purchase_date' => '2023-03-14', 'warranty_until' => '2028-03-14', 'supplier' => 'Securepoint über ALSO',
            'agent_identifier' => 'securepoint:'.$serial,
            'agent_reported_at' => now()->subMinutes(9),
            'agent_details' => [
                'interfaces' => [
                    ['name' => 'eth0 WAN', 'ip' => '203.0.113.18/28'],
                    ...$vlanInterfaces->map(fn ($k) => [
                        'name' => 'eth1.'.$this->vlan[$k]->vlanId.' '.$this->vlan[$k]->description,
                        'ip' => $this->vlan[$k]->gateway.'/24', 'vlan' => (string) $this->vlan[$k]->vlanId,
                    ])->all(),
                ],
                'vpns' => [
                    ['name' => 'Filiale München', 'type' => 'IPsec', 'remote' => '198.51.100.24'],
                    ['name' => 'Mitarbeiter', 'type' => 'SSL-VPN', 'remote' => 'vpn.mustermann.de'],
                    ['name' => 'Wartung Systemhaus', 'type' => 'WireGuard', 'remote' => '192.0.2.40'],
                ],
                'port_forwards' => [
                    ['protocol' => 'TCP', 'port' => '443', 'description' => 'Reverse-Proxy (remote., zammad., uma.)', 'target' => '172.16.10.10', 'target_port' => '443'],
                    ['protocol' => 'TCP', 'port' => '990', 'description' => 'FTPS Zeichnungsaustausch', 'target' => '172.16.10.10', 'target_port' => '990'],
                    ['protocol' => 'UDP', 'port' => '5060', 'description' => 'SIP-Trunk Telekom', 'target' => '10.10.40.10', 'target_port' => '5060'],
                ],
                'gateways' => [['name' => 'WAN Telekom', 'address' => '203.0.113.17']],
            ],
        ]);
        $this->ip($firewall, '203.0.113.18', 'wan', 'WAN');
        foreach ($vlanInterfaces as $key) {
            $this->ip($firewall, $this->vlan[$key]->gateway, $key, 'Gateway '.$this->vlan[$key]->description);
        }

        $munich = $this->make(Router::class, [
            'site_id' => $this->munich->id, 'name' => 'RTR-MUC', 'manufacturer' => 'Lancom', 'model' => '1900EF',
            'serialNumber' => '4006'.$this->serial('rtr-muc', 8), 'port' => 443,
            'purchase_date' => '2022-06-02', 'warranty_until' => '2027-06-02', 'supplier' => 'Bechtle AG',
        ]);
        $this->ip($munich, '198.51.100.24', null, 'WAN');
        foreach (['muc-clients', 'muc-voip', 'muc-guest'] as $key) {
            $this->ip($munich, $this->vlan[$key]->gateway, $key, 'Gateway '.$this->vlan[$key]->description);
        }

        // The mail archive: a Securepoint UMA as VM, archives Exchange Online by journal.
        $uma = $this->make(SecurepointUMA::class, [
            'name' => 'UMA-01', 'manufacturer' => 'Securepoint', 'type' => 'VM (Proxmox)',
            'urlAdmin' => 'https://10.10.30.35:11115', 'urlUser' => 'https://uma.mustermann.de',
            'encryptionkey' => 'UMA-Archiv-Schluessel-2024-9F3K',
        ]);
        $this->ip($uma, '10.10.30.35', 'server');
    }

    /**
     * The machines: the old Hyper-V host with its four Windows VMs, the
     * Proxmox cluster the services move to, and the backup server.
     */
    private function servers(): void
    {
        $dell = fn (string $name, string $model, int $units, array $extra) => $this->make(Server::class, [
            'site_id' => $this->hamburg->id, 'name' => $name, 'manufacturer' => 'Dell', 'model' => $model,
            'serialNumber' => $this->serial('srv-'.$name, 7), 'bmcUser' => 'root', 'bmcPassword' => 'iDrac!Werk2026',
            'form_factor' => 'rack', 'height_units' => $units, 'full_depth' => true, 'supplier' => 'Dell Technologies',
            ...$extra,
        ]);

        // Bought in 2019, warranty ends soon - the reason for the new cluster.
        $hv = $dell('SRV-HV01', 'PowerEdge R650', 1, [
            'operating_system_id' => $this->os('Windows Server 2019 Datacenter'), 'services' => 'Hyper-V',
            'cpu' => 'Intel Xeon Silver 4210R', 'cores' => 20, 'memory_gb' => 192, 'bmcIp' => '10.10.30.217',
            'remoteID' => $this->remoteId('SRV-HV01'), 'remotePassword' => 'Rd!HV01-2026',
            'purchase_date' => '2019-12-03', 'warranty_until' => now()->addMonths(2)->toDateString(),
            'agent_identifier' => $this->guid('win:srv-hv01'), 'agent_reported_at' => now()->subMinutes(7),
            'agent_details' => [
                'cpu' => 'Intel Xeon Silver 4210R @ 2.40GHz (20 Kerne)', 'cores' => 20, 'memory_gb' => 192,
                'storages' => [
                    ['name' => 'C: System', 'type' => 'ntfs', 'total_gb' => 446, 'used_gb' => 92],
                    ['name' => 'D: Hyper-V', 'type' => 'ntfs', 'total_gb' => 3576, 'used_gb' => 3180],
                ],
            ],
        ]);
        $this->ip($hv, '10.10.30.17', 'server');

        $bak = $dell('SRV-BAK01', 'PowerEdge R750xs', 2, [
            'operating_system_id' => $this->os('Windows Server 2022 Standard'), 'services' => 'Backup',
            'cpu' => 'Intel Xeon Silver 4309Y', 'cores' => 8, 'memory_gb' => 64, 'bmcIp' => '10.10.30.240',
            'remoteID' => $this->remoteId('SRV-BAK01'), 'remotePassword' => 'Rd!BAK01-2026',
            'purchase_date' => '2024-02-19', 'warranty_until' => '2029-02-19',
            'agent_identifier' => $this->guid('win:srv-bak01'), 'agent_reported_at' => now()->subMinutes(5),
        ]);
        $this->ip($bak, '10.10.30.40', 'server');
        $this->ip($bak, '10.10.80.40', 'backup', 'Backup-Netz');

        // The Hyper-V guests the host reports. SRV-ERP01 is the old ERP
        // server on Windows Server 2016 - entered by hand, its support has
        // ended (EOL badge), and it moves last.
        foreach ([
            ['SRV-DC01', 'Windows Server 2019 Standard', 'AD,DNS,DHCP', '10.10.30.10', 4, 8, 120, true],
            ['SRV-FS01', 'Windows Server 2019 Standard', 'FS,DFS,Print', '10.10.30.11', 4, 16, 2400, true],
            ['SRV-RDS01', 'Windows Server 2019 Standard', 'RDS', '10.10.30.12', 8, 32, 250, true],
            ['SRV-ERP01', 'Windows Server 2016 Standard', 'SQL', '10.10.30.16', 4, 24, 500, false],
        ] as [$name, $os, $services, $address, $cores, $ram, $disk, $byAgent]) {
            $vm = $this->make(VM::class, [
                'site_id' => $this->hamburg->id, 'server_id' => $hv->id, 'kind' => 'vm', 'name' => $name,
                'hostname' => mb_strtolower($name).'.ad.mustermann.de', 'operating_system_id' => $this->os($os),
                'services' => $services, 'cores' => $cores, 'memory_gb' => $ram, 'disk_gb' => $disk,
                'remoteID' => $this->remoteId($name), 'remotePassword' => 'Rd!'.substr($name, 4).'-2026',
                'agent_identifier' => $byAgent ? 'srv-hv01/'.$this->guid('hv:'.$name) : null,
                'agent_reported_at' => $byAgent ? now()->subMinutes(7) : null,
                'agent_details' => $byAgent ? ['status' => 'running', 'cores' => $cores, 'memory_gb' => $ram, 'disk_gb' => $disk] : null,
            ]);
            $this->ip($vm, $address, 'server');
        }

        // The Proxmox cluster - three equal nodes, Ceph across them.
        $cluster = $this->make(Cluster::class, [
            'site_id' => $this->hamburg->id, 'name' => 'PVE-Cluster HH', 'type' => 'ceph',
            'agent_identifier' => 'proxmox:pve-cluster-hh',
            'note' => 'Drei Knoten, Ceph auf NVMe (3 × 2 × 3,84 TB), Quorum über alle drei. Löst SRV-HV01 bis Ende 2026 ab.',
        ]);
        foreach ([['PVE-01', 13], ['PVE-02', 14], ['PVE-03', 15]] as [$node, $octet]) {
            $server = $dell($node, 'PowerEdge R650', 1, [
                'cluster_id' => $cluster->id, 'operating_system_id' => $this->os('Proxmox VE 9'), 'services' => 'PVE',
                'cpu' => 'Intel Xeon Silver 4314', 'cores' => 32, 'memory_gb' => 256, 'bmcIp' => '10.10.30.2'.$octet,
                'purchase_date' => '2025-09-08', 'warranty_until' => '2030-09-08',
            ]);
            $this->ip($server, '10.10.30.'.$octet, 'server');
            $this->ip($server, '10.10.80.'.$octet, 'backup', 'Ceph / Backup');
        }

        // What the Proxmox agent reports: node details and the guests.
        $this->call(DemoProxmoxSeeder::class);

        // Address and services of the reported guests - what the agent
        // does not know and the technician added.
        foreach ([
            ['SRV-DC02', '10.10.30.20', 'server', 'AD,DNS,DHCP,PKI'],
            ['SRV-SQL01', '10.10.30.21', 'server', 'SQL'],
            ['SRV-APP01', '10.10.30.22', 'server', 'IIS'],
            ['SRV-FS02', '10.10.30.23', 'server', 'FS,DFS'],
            ['SRV-WSUS01', '10.10.30.24', 'server', 'WSUS'],
            ['CT-UniFi', '10.10.30.30', 'server', 'Web'],
            ['CT-Grafana', '10.10.30.31', 'server', 'Monitoring'],
            ['CT-Test', '10.10.30.32', 'server', null],
            ['CT-Proxy', '172.16.10.10', 'dmz', 'Web'],
            ['PBS01', '10.10.80.50', 'backup', 'PBS'],
        ] as [$name, $address, $vlan, $services]) {
            $vm = $this->device(VM::class, $name);
            if (! $vm) {
                continue;
            }
            $vm->forceFill(['services' => $services, 'hostname' => mb_strtolower($name).'.ad.mustermann.de'])->save();
            $this->ip($vm, $address, $vlan);
        }

        // Two VMs entered by hand, at the cluster rather than a node: in
        // the HA cluster they move between the nodes.
        foreach ([
            ['SRV-ZAMMAD', 'Docker,Web', '10.10.30.25', 4, 8, 120],
            ['SRV-CHECKMK', 'Monitoring', '10.10.30.26', 2, 8, 80],
        ] as [$name, $services, $address, $cores, $ram, $disk]) {
            $vm = $this->make(VM::class, [
                'site_id' => $this->hamburg->id, 'cluster_id' => $cluster->id, 'kind' => 'vm', 'name' => $name,
                'hostname' => mb_strtolower($name).'.ad.mustermann.de', 'operating_system_id' => $this->os('Debian 13'),
                'services' => $services, 'cores' => $cores, 'memory_gb' => $ram, 'disk_gb' => $disk,
            ]);
            $this->ip($vm, $address, 'server');
        }

        // The domain controllers, the CA and Entra Connect of the domain.
        foreach ([['SRV-DC01', 'dc'], ['SRV-DC02', 'dc'], ['SRV-DC01', 'entra_connect'], ['SRV-DC02', 'ca']] as [$name, $role]) {
            if ($vm = $this->device(VM::class, $name)) {
                ADDomainHost::create(['ad_domain_id' => $this->domain->id, 'role' => $role, 'host_type' => VM::class, 'host_id' => $vm->id]);
            }
        }
    }

    private function storage(): void
    {
        foreach ([
            ['NAS-Backup', 'RS1221+', '10.10.80.20', 'backup', '2024-02-19'],
            ['NAS-Archiv', 'RS822+', '10.10.30.42', 'server', '2022-11-07'],
        ] as [$name, $model, $address, $vlan, $bought]) {
            $nas = $this->make(NAS::class, [
                'site_id' => $this->hamburg->id, 'name' => $name, 'manufacturer' => 'Synology', 'model' => $model,
                'serialNumber' => $this->serial('nas-'.$name, 13), 'firmware' => 'DSM 7.2.2-72806 Update 3', 'port' => 5001,
                'purchase_date' => $bought, 'warranty_until' => date('Y-m-d', strtotime($bought.' +5 years')), 'supplier' => 'Bechtle AG',
            ]);
            $this->ip($nas, $address, $vlan);
        }
    }

    /**
     * UniFi throughout - the controller runs in CT-UniFi, and the agent
     * there reports switches, access points and Wi-Fis. One access point is
     * new and not adopted yet: entered by hand.
     */
    private function switchesAndWifi(): void
    {
        foreach ([
            ['SW-Core', 'USW-Pro-24-PoE', '10.10.30.2', 'hh', 'Serverraum EG, Rack HH-01'],
            ['SW-EG-01', 'USW-Pro-48-PoE', '10.10.30.3', 'hh', 'Serverraum EG, Rack HH-01'],
            ['SW-OG-01', 'USW-Pro-48-PoE', '10.10.30.4', 'hh', 'Etagenverteiler 1. OG'],
            ['SW-Halle', 'USW-Pro-24-PoE', '10.10.30.5', 'hh', 'Halle 1, Wandschrank'],
            ['SW-MUC-01', 'USW-Pro-24-PoE', '10.20.20.2', 'muc', 'Technikraum Filiale'],
        ] as [$name, $model, $address, $site, $location]) {
            $switch = $this->make(NetworkSwitch::class, [
                'site_id' => $this->site($site), 'name' => $name,
                'manufacturer' => 'Ubiquiti', 'model' => $model, 'serialNumber' => str_replace(':', '', $this->mac('24:5A:4C', $name)),
                'firmware' => '7.1.26', 'location' => $location, 'agent_identifier' => 'unifi:'.mb_strtolower($this->mac('24:5a:4c', $name)),
                'purchase_date' => '2024-04-22', 'warranty_until' => '2028-04-22', 'supplier' => 'Ingram Micro',
            ]);
            $this->ip($switch, $address, $site === 'hh' ? 'server' : 'muc-clients');
        }

        foreach ([
            ['AP-EG-Empfang', 'U6-Pro', '10.10.30.6', 'hh', 'Empfang, Decke', true],
            ['AP-EG-Büro', 'U6-Pro', '10.10.30.7', 'hh', 'Büro Buchhaltung', true],
            ['AP-OG-Büro', 'U6-Pro', '10.10.30.8', 'hh', 'Großraum 1. OG', true],
            ['AP-Halle-01', 'U6-Enterprise', '10.10.30.9', 'hh', 'Halle 1, Hallenmitte', true],
            ['AP-Lager', 'U6-Lite', null, 'hh', 'Lager, Rampe - neu, noch nicht im Controller', false],
            ['AP-MUC-01', 'U6-Lite', '10.20.20.3', 'muc', 'Büro Filiale', true],
        ] as [$name, $model, $address, $site, $location, $adopted]) {
            $ap = $this->make(Accesspoint::class, [
                'site_id' => $this->site($site), 'name' => $name,
                'manufacturer' => 'Ubiquiti', 'model' => $model, 'serialNumber' => str_replace(':', '', $this->mac('9C:05:D6', $name)),
                'firmware' => $adopted ? '6.6.77' : null, 'location' => $location,
                'agent_identifier' => $adopted ? 'unifi:'.mb_strtolower($this->mac('9c:05:d6', $name)) : null,
                'purchase_date' => $adopted ? '2024-04-22' : now()->subDays(6)->toDateString(), 'supplier' => 'Ingram Micro',
            ]);
            if ($address) {
                $this->ip($ap, $address, $site === 'hh' ? 'server' : 'muc-clients');
            }
        }

        foreach ([
            ['Mustermann', 'clients', 'hh', 'WPA2/WPA3', 'Werkstrasse14!WLAN'],
            ['Mustermann-Gast', 'guest', 'hh', 'WPA2', 'Gast-Hamburg-2026'],
            ['Mustermann-Halle', 'production', 'hh', 'WPA2', 'Halle!Scanner-90'],
            ['Mustermann', 'muc-clients', 'muc', 'WPA2/WPA3', 'Gewerbering7!WLAN'],
        ] as [$ssid, $vlan, $site, $encryption, $password]) {
            $this->make(Wifi::class, [
                'site_id' => $this->site($site), 'ssid' => $ssid,
                'network_id' => $this->vlan[$vlan]->id, 'encryption' => $encryption, 'password' => $password,
                'agent_identifier' => 'unifi:wlan:'.substr(md5($ssid.$site), 0, 24),
            ]);
        }
    }

    /**
     * One computer per desk. Most report through the Windows agent; two are
     * documented by hand. One still runs Windows 10 - past its support.
     */
    private function clients(array $people): void
    {
        foreach ([
            ['PC-EMPFANG', 'petra.schulz', 'OptiPlex 7010 Micro', 'Windows 11 Pro', '10.10.20.50', true],
            ['NB-GF-01', 'max.mustermann', 'Latitude 7440', 'Windows 11 Pro', null, true],
            ['PC-BUHA-01', 'erika.mustermann', 'OptiPlex 7010', 'Windows 11 Pro', null, true],
            ['PC-BUHA-02', 'anna.richter', 'OptiPlex 7010', 'Windows 11 Pro', null, true],
            ['PC-EINKAUF', 'sabine.roth', 'OptiPlex 7010', 'Windows 11 Pro', null, true],
            ['NB-VERTRIEB-01', 'jonas.becker', 'Latitude 5540', 'Windows 11 Pro', null, true],
            ['NB-VERTRIEB-02', 'laura.schneider', 'Latitude 5540', 'Windows 11 Pro', null, false],
            ['WS-KONSTR-01', 'tobias.wagner', 'Precision 3660', 'Windows 11 Pro', '10.10.20.21', true],
            ['WS-KONSTR-02', 'sandra.hoffmann', 'Precision 3660', 'Windows 11 Pro', '10.10.20.22', true],
            ['PC-FERTIGUNG', 'michael.krueger', 'OptiPlex 7090', 'Windows 10 Pro', null, false],
            ['PC-LAGER', 'dennis.wolf', 'OptiPlex 3000', 'Windows 11 Pro', null, true],
            ['NB-MUC-01', 'stefan.braun', 'Latitude 5540', 'Windows 11 Pro', null, true],
            ['PC-MUC-01', 'julia.neumann', 'OptiPlex 7010', 'Windows 11 Pro', null, true],
        ] as [$name, $owner, $model, $os, $fixed, $byAgent]) {
            $munich = $people[$owner][5] === 'muc';
            $old = $os === 'Windows 10 Pro';
            $pc = $this->make(Computer::class, [
                'site_id' => $this->site($munich ? 'muc' : 'hh'), 'name' => $name,
                'manufacturer' => 'Dell', 'model' => $model, 'serialNumber' => $this->serial('pc-'.$name, 7),
                'operating_system_id' => $this->os($os),
                'remoteID' => $this->remoteId($name), 'remotePassword' => 'Rd!'.$name,
                'agent_identifier' => $byAgent ? $this->guid('client:'.$name) : null,
                'purchase_date' => $old ? '2021-05-11' : '2024-01-15',
                'warranty_until' => $old ? '2024-05-11' : '2029-01-15', 'supplier' => 'Dell Technologies',
            ]);
            $fixed
                ? $this->ip($pc, $fixed, 'clients')
                : $this->ip($pc, null, $munich ? 'muc-clients' : 'clients', 'Primär', true);
        }

        // Production terminals and the screen at reception.
        foreach ([
            ['TC-HALLE-01', 'IGEL', 'UD3 M350C', '10.10.90.30', 'production'],
            ['TC-HALLE-02', 'IGEL', 'UD3 M350C', '10.10.90.31', 'production'],
            ['Info-Display Empfang', 'Samsung', 'QB43R', '10.10.20.45', 'clients'],
        ] as [$name, $maker, $model, $address, $vlan]) {
            $client = $this->make(OtherClient::class, [
                'site_id' => $this->hamburg->id, 'name' => $name, 'manufacturer' => $maker, 'model' => $model,
                'serialNumber' => $this->serial('oc-'.$name), 'purchase_date' => '2023-09-01', 'supplier' => 'Bechtle AG',
            ]);
            $this->ip($client, $address, $vlan);
        }
    }

    private function printing(): void
    {
        foreach ([
            ['PRN-Empfang', 'Kyocera', 'ECOSYS MA4500ifx', '10.10.60.20', 'hh'],
            ['PRN-OG-Büro', 'Kyocera', 'TASKalfa 2554ci', '10.10.60.21', 'hh'],
            ['PLT-Konstruktion', 'HP', 'DesignJet T650', '10.10.60.22', 'hh'],
            ['PRN-Lager', 'Kyocera', 'ECOSYS P3150dn', '10.10.60.23', 'hh'],
            ['PRN-MUC', 'Kyocera', 'ECOSYS MA4000cifx', '10.20.20.20', 'muc'],
        ] as [$name, $maker, $model, $address, $site]) {
            $printer = $this->make(Printer::class, [
                'site_id' => $this->site($site), 'name' => $name,
                'manufacturer' => $maker, 'model' => $model, 'serialNumber' => $this->serial('prn-'.$name), 'port' => 443,
                'purchase_date' => '2023-06-30', 'warranty_until' => '2027-06-30', 'supplier' => 'Kyocera Partner Nord',
            ]);
            $this->ip($printer, $address, $site === 'hh' ? 'printers' : 'muc-clients');
        }

        $scanners = collect([
            ['SCN-Buchhaltung', 'Ricoh', 'fi-8170', '10.10.60.30'],
            ['SCN-Empfang', 'Brother', 'ADS-4700W', '10.10.60.31'],
        ])->map(function ($row) {
            [$name, $maker, $model, $address] = $row;
            $scanner = $this->make(Scanner::class, [
                'site_id' => $this->hamburg->id, 'name' => $name, 'manufacturer' => $maker, 'model' => $model,
                'serialNumber' => $this->serial('scn-'.$name), 'purchase_date' => '2023-06-30', 'supplier' => 'Bechtle AG',
            ]);
            $this->ip($scanner, $address, 'printers');

            return $scanner;
        });

        $scanLogin = $this->make(LoginGeneral::class, [
            'name' => 'Scanner SFTP', 'description' => 'Konto der Scanner für das Archiv auf NAS-Archiv',
            'username' => 'scan', 'password' => 'Sc4n!Archiv-2026',
        ]);
        $targets = collect([
            ['name' => 'Buchhaltung', 'kind' => 'smb', 'target' => '\\\\SRV-FS01\\Scans\\Buchhaltung', 'description' => 'Eingangsrechnungen, holt DATEV ab'],
            ['name' => 'Empfang', 'kind' => 'email', 'target' => 'scan@mustermann.de', 'description' => 'Geht an das Postfach Scan'],
            ['name' => 'Archiv', 'kind' => 'ftp', 'target' => 'sftp://nas-archiv.ad.mustermann.de/scans', 'login_general_id' => $scanLogin->id, 'description' => 'Revisionssicheres Archiv'],
        ])->map(fn ($target) => $this->make(ScanTarget::class, $target));
        $scanners->each(fn (Scanner $s) => $s->scanTargets()->attach($targets->pluck('id')));
    }

    /**
     * 3CX on a SIP trunk of the Telekom, Yealink phones with the extension
     * of their owner, DECT in the hall and the warehouse. Munich hangs on
     * the same 3CX with numbers of its own.
     */
    private function telephony(array $people): void
    {
        $pbx = $this->make(PhoneSystem::class, [
            'site_id' => $this->hamburg->id, 'name' => 'TK-3CX', 'manufacturer' => '3CX', 'model' => 'v20 Pro (16 SC)',
            'type' => 'Software (Debian-VM)', 'serialNumber' => '3CX'.$this->serial('3cx', 12), 'port' => 5001,
            'purchase_date' => '2024-10-01', 'warranty_until' => now()->addMonths(10)->toDateString(), 'supplier' => '3CX-Partner Nord',
        ]);
        $this->ip($pbx, '10.10.40.10', 'voip');

        $hhLine = InternetConnection::where('customer_id', $this->customer->id)->where('site_id', $this->hamburg->id)->value('id');
        $this->make(SipAccount::class, [
            'site_id' => $this->hamburg->id, 'provider' => 'Deutsche Telekom', 'account_type' => 'trunk',
            'product' => 'DeutschlandLAN SIP-Trunk', 'contract_number' => 'DTAG-7300418853', 'provider_customer_number' => '5501 2234 01',
            'hotline' => '0800 330 4711', 'main_number' => '040 7541 32', 'number_range' => '040 7541 32-0 bis -99',
            'channels' => 10, 'registrar' => 'reg.sip-trunk.telekom.de', 'phone_system_id' => $pbx->id, 'internet_connection_id' => $hhLine,
            'notes' => 'Zentrale 040 7541 32-0, Fax 040 7541 32-99.',
        ]);
        $this->make(SipAccount::class, [
            'site_id' => $this->munich->id, 'provider' => 'sipgate', 'account_type' => 'single',
            'product' => 'sipgate trunking (Einzelnummern)', 'provider_customer_number' => 'SG-8841203',
            'hotline' => '0211 6355 4711', 'numbers' => "089 8973 1260\n089 8973 1261", 'channels' => 2,
            'registrar' => 'sipconnect.sipgate.de', 'phone_system_id' => $pbx->id,
        ]);

        $i = 0;
        foreach ($people as $username => [, , , $extension, , $site]) {
            if ($extension === null) {
                continue;
            }
            $phone = $this->make(Phone::class, [
                'site_id' => $this->site($site), 'manufacturer' => 'Yealink', 'model' => in_array($extension, [10, 11, 12], true) ? 'T54W' : 'T46U',
                'serialNumber' => '8024'.$this->serial('tel-'.$username, 12), 'mac' => $this->mac('80:5E:C0', $username),
                'extension' => (string) $extension, 'port' => 443, 'purchase_date' => '2024-10-01', 'supplier' => 'Bechtle AG',
            ]);
            $site === 'muc'
                ? $this->ip($phone, '10.20.40.'.(20 + $extension - 60), 'muc-voip')
                : $this->ip($phone, '10.10.40.'.(20 + $i++), 'voip');
        }

        foreach ([['Master', 'Halle 1', '10.10.40.40'], ['Slave', 'Lager', '10.10.40.41']] as [$role, $location, $address]) {
            $dect = $this->make(DECT::class, [
                'site_id' => $this->hamburg->id, 'manufacturer' => 'Yealink', 'model' => 'W80B', 'role' => $role,
                'serialNumber' => '8025'.$this->serial('dect-'.$role, 12), 'mac' => $this->mac('80:5E:C0', 'dect'.$role),
                'location' => $location, 'port' => 443, 'purchase_date' => '2024-10-01', 'supplier' => 'Bechtle AG',
            ]);
            $this->ip($dect, $address, 'voip');
        }
    }

    /** UniFi Protect: one recorder per site, the cameras around the halls. */
    private function security(): void
    {
        $recorders = [];
        foreach ([['NVR-Zentrale', 'UNVR-Pro', '10.10.70.10', 'hh', 'cameras'], ['NVR-MUC', 'Cloud Key G2+', '10.20.20.6', 'muc', 'muc-clients']] as [$name, $model, $address, $site, $vlan]) {
            $recorders[$name] = $this->make(Recorder::class, [
                'site_id' => $this->site($site), 'name' => $name,
                'manufacturer' => 'Ubiquiti', 'model' => $model, 'serialNumber' => str_replace(':', '', $this->mac('E4:38:83', $name)), 'port' => 443,
                'purchase_date' => '2024-04-22', 'warranty_until' => '2028-04-22', 'supplier' => 'Ingram Micro',
            ]);
            $this->ip($recorders[$name], $address, $vlan);
        }

        $i = 0;
        foreach ([
            ['CAM-Einfahrt', 'G5 Bullet', 'hh', 'Tor zur Werkstraße'],
            ['CAM-Hof', 'G5 Pro', 'hh', 'Hof, Blick auf Halle 1'],
            ['CAM-Parkplatz', 'G5 Bullet', 'hh', 'Mitarbeiterparkplatz'],
            ['CAM-Halle-1', 'G5 Dome', 'hh', 'Halle 1, Laser'],
            ['CAM-Halle-2', 'G5 Dome', 'hh', 'Halle 2, Abkantpressen'],
            ['CAM-Lager', 'G5 Dome', 'hh', 'Lager, Hochregal'],
            ['CAM-Versand', 'G5 Bullet', 'hh', 'Rampe Versand'],
            ['CAM-Empfang', 'G5 Flex', 'hh', 'Eingang Empfang'],
            ['CAM-MUC-Eingang', 'G5 Flex', 'muc', 'Eingang Filiale'],
        ] as [$name, $model, $site, $location]) {
            $camera = $this->make(Camera::class, [
                'site_id' => $this->site($site), 'name' => $name,
                'manufacturer' => 'Ubiquiti', 'model' => $model, 'serialNumber' => str_replace(':', '', $this->mac('E4:38:83', $name)),
                'firmware' => '4.75.43', 'location' => $location, 'port' => 443,
                'purchase_date' => '2024-04-22', 'supplier' => 'Ingram Micro',
            ]);
            $site === 'hh'
                ? $this->ip($camera, '10.10.70.'.(20 + $i++), 'cameras')
                : $this->ip($camera, '10.20.20.7', 'muc-clients');
        }

        foreach ($recorders as $name => $recorder) {
            $this->link($recorder, $this->make(LoginGeneral::class, [
                'name' => $name.' (Protect)', 'description' => 'Lokaler Admin der UniFi-Protect-Konsole',
                'username' => 'admin', 'password' => 'Protect!'.substr($name, 4).'-26',
            ]));
        }
    }

    /** The machines of the halls, the building, the uninterruptible power. */
    private function production(): void
    {
        foreach ([
            ['Laserschneidanlage TruLaser 3030 (Halle 1)', '10.10.90.20'],
            ['CNC-Fräse DMG MORI CMX 600 V (Halle 1)', '10.10.90.21'],
            ['Abkantpresse TruBend 5130 (Halle 2)', '10.10.90.22'],
            ['Etikettendrucker Versand Zebra ZT411', '10.10.90.23'],
        ] as [$name, $address]) {
            $machine = $this->make(Machine::class, ['site_id' => $this->hamburg->id, 'name' => $name]);
            $this->ip($machine, $address, 'production');
        }

        foreach ([
            ['Zeiterfassung Halle', 'Reiner SCT', 'timeCard 6 Terminal', '10.10.90.40', 'production', 'Halle 1, Eingang'],
            ['Zutrittskontrolle', 'PCS Systemtechnik', 'INTUS 5540', '10.10.100.20', 'building', 'Haupteingang'],
            ['Türsprechanlage', 'DoorBird', 'D1101V', '10.10.100.21', 'building', 'Tor Werkstraße'],
            ['Hallenlüftung', 'Siemens', 'LOGO! 8.4', '10.10.100.22', 'building', 'Halle 1, Schaltschrank'],
        ] as [$name, $maker, $model, $address, $vlan, $location]) {
            $iot = $this->make(IoTDevice::class, [
                'site_id' => $this->hamburg->id, 'name' => $name, 'manufacturer' => $maker, 'model' => $model,
                'serialNumber' => $this->serial('iot-'.$name), 'url' => 'http://'.$address, 'location' => $location,
                'purchase_date' => '2022-08-15', 'supplier' => $maker,
            ]);
            $this->ip($iot, $address, $vlan);
        }

        foreach ([
            ['USV-01', 'Smart-UPS 1500 RM', '1500VA', '24 Min', '10.10.30.50', 'hh', null],
            ['USV-02', 'Smart-UPS SRT 3000 RM', '3000VA', '16 Min', '10.10.30.51', 'hh', 'Akkutausch zuletzt 03/2025'],
            ['USV-OG', 'Back-UPS Pro 900', '900VA', '35 Min', null, 'hh', 'Etagenverteiler 1. OG, ohne Netzwerkkarte'],
            ['USV-MUC', 'Smart-UPS 750 RM', '750VA', '30 Min', '10.20.20.5', 'muc', null],
        ] as [$name, $model, $capacity, $runtime, $address, $site, $notes]) {
            $ups = $this->make(Ups::class, [
                'site_id' => $this->site($site), 'name' => $name,
                'manufacturer' => 'APC', 'model' => $model, 'serialNumber' => 'AS'.$this->serial('ups-'.$name, 10),
                'capacity' => $capacity, 'runtime' => $runtime, 'notes' => $notes,
                'purchase_date' => '2023-03-01', 'warranty_until' => '2028-03-01', 'supplier' => 'Bechtle AG',
            ]);
            if ($address) {
                $this->ip($ups, $address, $site === 'hh' ? 'server' : 'muc-clients');
            }
        }
    }

    /**
     * The racks, bottom up: power, servers, storage - network and patch
     * panels at the top. Passive parts from the rack catalogue.
     */
    private function racks(): void
    {
        $catalogue = function (int $position, string $name, string $side = 'front') {
            $item = RackCatalogItem::where('name', $name)->first();

            return $item ? ['side' => $side, 'position' => $position, 'height_units' => $item->height_units,
                'full_depth' => $item->full_depth, 'name' => $item->name, 'appearance' => $item->appearance] : null;
        };
        $device = function (int $position, string $class, string $name, int $units = 1) {
            $model = $this->device($class, $name);

            return $model ? ['position' => $position, 'height_units' => (int) ($model->height_units ?? $units),
                'device_type' => $class, 'device_id' => $model->id] : null;
        };

        $panel = function (string $name, Site $site, int $ports, int $units, string $switch, array $outlets) {
            $panel = $this->make(PatchPanel::class, [
                'site_id' => $site->id, 'name' => $name, 'port_count' => $ports, 'height_units' => $units,
                'manufacturer' => 'Telegärtner', 'model' => 'AMJ-S Cat.6A',
            ]);
            $panel->syncPorts();
            $switchId = $this->device(NetworkSwitch::class, $switch)?->id;
            foreach ($outlets as [$number, $outlet, $room]) {
                $panel->ports()->where('number', $number)->update([
                    'outlet' => $outlet, 'label' => $room, 'network_switch_id' => $switchId, 'switch_port' => (string) $number,
                ]);
            }

            return $panel;
        };

        $pfEg1 = $panel('PF-EG-01', $this->hamburg, 24, 1, 'SW-EG-01', [
            [1, 'EG 1.01', 'Empfang'], [2, 'EG 1.02', 'Empfang'], [3, 'EG 1.03', 'Info-Display'],
            [5, 'EG 1.05', 'Büro Geschäftsführung'], [7, 'EG 1.07', 'Buchhaltung'], [8, 'EG 1.08', 'Buchhaltung'],
            [11, 'EG 1.11', 'Einkauf'], [14, 'EG 1.14', 'Besprechung'], [20, 'EG 1.20', 'Drucker Empfang'],
        ]);
        $pfEg2 = $panel('PF-EG-02', $this->hamburg, 24, 1, 'SW-EG-01', [
            [1, 'H1.01', 'Halle 1, Laser'], [2, 'H1.02', 'Halle 1, CNC'], [4, 'H1.04', 'Halle 1, Terminal'],
            [9, 'H2.01', 'Halle 2, Abkantpresse'], [12, 'L.01', 'Lager, PC'], [13, 'L.02', 'Lager, Drucker'],
        ]);
        $pfOg1 = $panel('PF-OG-01', $this->hamburg, 48, 2, 'SW-OG-01', [
            [1, 'OG 1.01', 'Vertrieb'], [2, 'OG 1.02', 'Vertrieb'], [9, 'OG 1.09', 'Konstruktion'],
            [10, 'OG 1.10', 'Konstruktion'], [11, 'OG 1.11', 'Plotter'], [26, 'OG 1.26', 'Konferenz'],
        ]);
        $pfOg2 = $panel('PF-OG-02', $this->hamburg, 24, 1, 'SW-OG-01', [
            [4, 'OG 2.04', 'Kopierer'], [5, 'OG 2.05', 'Teeküche'],
        ]);
        $pfMuc = $panel('PF-MUC-01', $this->munich, 24, 1, 'SW-MUC-01', [
            [1, 'MUC 1.01', 'Büro Leitung'], [2, 'MUC 1.02', 'Büro Vertrieb'], [8, 'MUC 1.08', 'Drucker'],
        ]);
        $panelItem = fn (int $position, PatchPanel $panel) => ['position' => $position, 'height_units' => $panel->height_units,
            'device_type' => PatchPanel::class, 'device_id' => $panel->id];

        $keep = fn ($item) => $item !== null && ($item['device_id'] ?? true) !== null;

        $rack = $this->make(Rack::class, ['site_id' => $this->hamburg->id, 'name' => 'Rack HH-01', 'height_units' => 42, 'location' => 'Serverraum EG', 'note' => 'Klimatisiert, Schlüssel beim Empfang.']);
        $rack->items()->createMany(array_values(array_filter([
            $device(1, Ups::class, 'USV-02', 2),
            $device(3, Ups::class, 'USV-01', 2),
            $device(6, Server::class, 'SRV-BAK01'),
            $device(8, NAS::class, 'NAS-Backup', 2),
            $device(10, NAS::class, 'NAS-Archiv', 1),
            $device(12, Server::class, 'PVE-01'),
            $device(13, Server::class, 'PVE-02'),
            $device(14, Server::class, 'PVE-03'),
            $catalogue(15, 'Blindplatte 2 HE'),
            $device(18, Server::class, 'SRV-HV01'),
            $catalogue(20, 'Fachboden 1 HE'),
            $device(30, Firewall::class, 'FW-HH'),
            $device(31, Router::class, 'RTR-HH-Telekom'),
            $device(34, NetworkSwitch::class, 'SW-Core'),
            $device(36, NetworkSwitch::class, 'SW-EG-01'),
            $panelItem(37, $pfEg1),
            $catalogue(38, 'Rangierfeld'),
            $panelItem(39, $pfEg2),
            $catalogue(42, 'Kabeldurchführung'),
            $catalogue(3, 'Steckdosenleiste (PDU)', 'rear'),
            $catalogue(41, 'Kabeldurchführung', 'rear'),
        ], $keep)));

        $rackOg = $this->make(Rack::class, ['site_id' => $this->hamburg->id, 'name' => 'Rack HH-02', 'height_units' => 12, 'location' => 'Etagenverteiler 1. OG']);
        $rackOg->items()->createMany(array_values(array_filter([
            $device(8, NetworkSwitch::class, 'SW-OG-01'),
            $catalogue(9, 'Rangierfeld'),
            $panelItem(10, $pfOg1),
            $panelItem(12, $pfOg2),
            $catalogue(1, 'Steckdosenleiste (PDU)', 'rear'),
        ], $keep)));

        $rackMuc = $this->make(Rack::class, ['site_id' => $this->munich->id, 'name' => 'Rack MUC-01', 'height_units' => 12, 'location' => 'Technikraum Filiale']);
        $rackMuc->items()->createMany(array_values(array_filter([
            $device(1, Ups::class, 'USV-MUC', 2),
            $device(6, Router::class, 'RTR-MUC'),
            $device(9, NetworkSwitch::class, 'SW-MUC-01'),
            $catalogue(10, 'Rangierfeld'),
            $panelItem(11, $pfMuc),
            $catalogue(1, 'Steckdosenleiste (PDU)', 'rear'),
        ], $keep)));
    }

    /**
     * Credentials: one password linked to many systems is the case the
     * links exist for. One note where it adds something (serial console).
     */
    private function logins(): void
    {
        $login = fn (string $name, string $description, string $username, string $password) => $this->make(LoginGeneral::class, [
            'name' => $name, 'description' => $description, 'username' => $username, 'password' => $password,
        ]);

        $domainAdmin = $login('Domänen-Administrator', 'Nur für Notfälle - im Alltag die persönlichen Admin-Konten', 'MUSTERMANN\\administrator', 'Dom!Admin-Werk-2026');
        $hostLocal = $login('Server lokal Administrator', 'Lokale Anmeldung an SRV-HV01 und SRV-BAK01', '.\\Administrator', 'Hyp3r!Demo2026');
        $idrac = $login('iDRAC root', 'Fernverwaltung der Dell-Server', 'root', 'iDrac!Werk2026');
        $pveRoot = $login('Proxmox root@pam', 'Weboberfläche und SSH der Clusterknoten', 'root', 'Pve!Cluster-HH-26');
        $linuxRoot = $login('Linux root', 'Einheitliches root-Passwort der Container und Linux-VMs', 'root', 'R00t!Demo2026');
        $fwAdmin = $login('Securepoint admin', 'Weboberfläche FW-HH, Port 11115', 'admin', 'Utm!Werk-Hamburg-26');
        $unifi = $login('UniFi Controller', 'Netzwerk-Controller auf CT-UniFi', 'mustermann-admin', 'UniFi!Netz-2026');
        $pbx = $login('3CX Verwaltung', 'Admin-Konsole der TK-Anlage', 'admin', '3cx!Tk-Werk-2026');
        $login('Microsoft 365 Admin', 'Globaler Administrator (Break-Glass-Konto)', 'admin@mustermann.onmicrosoft.com', 'M365!Glass-Break-26');
        $login('DATEV Unternehmen online', 'Belegtransfer ans Steuerbüro Lange', 'mustermann-gmbh', 'Datev!Belege-2026');
        $erp = $login('abas ERP Administrator', 'Warenwirtschaft, Anmeldung am Client', 'erpadmin', 'Erp!abas-2026');
        $login('Zeiterfassung Reiner SCT', 'timeCard-Software auf SRV-APP01', 'zeitadmin', 'Zeit!Halle-2026');

        foreach (['SRV-DC01', 'SRV-DC02'] as $dc) {
            $this->link($this->device(VM::class, $dc), $domainAdmin);
        }
        foreach (['SRV-HV01', 'SRV-BAK01'] as $host) {
            $this->link($this->device(Server::class, $host), $hostLocal);
        }
        foreach (Server::where('customer_id', $this->customer->id)->get() as $server) {
            $this->link($server, $idrac, 'iDRAC');
        }
        foreach (['PVE-01', 'PVE-02', 'PVE-03'] as $node) {
            $this->link($this->device(Server::class, $node), $pveRoot);
        }
        foreach (VM::where('customer_id', $this->customer->id)->where(fn ($q) => $q->where('kind', 'lxc')->orWhereIn('name', ['SRV-ZAMMAD', 'SRV-CHECKMK']))->get() as $linux) {
            $this->link($linux, $linuxRoot);
        }
        $firewall = $this->device(Firewall::class, 'FW-HH');
        $this->link($firewall, $fwAdmin);
        // The same root password at the firewall - over the serial console.
        $this->link($firewall, $linuxRoot, 'Serielle Konsole');
        $this->link($this->device(VM::class, 'CT-UniFi'), $unifi);
        $this->link($this->device(PhoneSystem::class, 'TK-3CX'), $pbx);
        $this->link($this->device(VM::class, 'SRV-APP01'), $erp);
        $this->link($this->device(VM::class, 'SRV-ERP01'), $erp);

        foreach (['NAS-Backup', 'NAS-Archiv'] as $nas) {
            $this->link($this->device(NAS::class, $nas), $login($nas.' (admin)', 'DSM-Weboberfläche', 'admin', 'Dsm!'.substr($nas, 4).'-2026'));
        }

        // Two SSH keys: one for all Linux systems, one only for the nightly copy.
        $adminKey = SshKey::create([
            'customer_id' => $this->customer->id, 'name' => 'Admin ed25519', 'description' => 'Wartungszugang der Linux-Systeme',
            'username' => 'root', 'key_type' => 'ed25519',
            'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIHk'.$this->serial('ssh-admin', 32).' admin@mustermann',
            'private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----\nb3BlbnNzaC1rZXktdjEAAAAA".$this->serial('ssh-admin-priv', 48)."\n-----END OPENSSH PRIVATE KEY-----",
            'password' => 'Ssh!Passphrase-2026',
        ]);
        SshKey::create([
            'customer_id' => $this->customer->id, 'name' => 'Backup rsa', 'description' => 'Nur für die nächtliche Auslagerung auf NAS-Archiv, ohne Passphrase',
            'username' => 'backup', 'key_type' => 'rsa',
            'public_key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQ'.$this->serial('ssh-backup', 48).' backup@mustermann',
            'private_key' => "-----BEGIN RSA PRIVATE KEY-----\nMIIG4wIBAAKCAYEA".$this->serial('ssh-backup-priv', 64)."\n-----END RSA PRIVATE KEY-----",
        ]);
        foreach (['PVE-01', 'PVE-02', 'PVE-03'] as $node) {
            $this->link($this->device(Server::class, $node), $adminKey, 'SSH');
        }

        foreach ([
            ['Telekom Geschäftskunden-Portal', 'https://geschaeftskunden.telekom.de', '5501223401', 'Tk!Portal-Werk-26'],
            ['IONOS Kundenkonto', 'https://login.ionos.de', '412987331', 'Ionos!Domains-26'],
            ['Vodafone Business', 'https://www.vodafone.de/business', 'mustermann-muc', 'Vf!Filiale-2026'],
            ['Securepoint Reseller-Portal', 'https://my.securepoint.cloud', 'it@mustermann.de', 'Sp!Portal-2026'],
            ['Dell TechDirect', 'https://tdm.dell.com', 'it@mustermann.de', 'Dell!Garantie-26'],
            ['Würth Online-Shop', 'https://eshop.wuerth.de', 'einkauf@mustermann.de', 'Wuerth!Einkauf-26'],
            ['MyTRUMPF Serviceportal', 'https://www.trumpf.com/mytrumpf', 'michael.krueger@mustermann.de', 'Trumpf!Halle-26'],
        ] as [$name, $url, $username, $password]) {
            $this->make(LoginWebsite::class, ['name' => $name, 'url' => $url, 'username' => $username, 'password' => $password]);
        }
    }

    /**
     * Licences that fit what is there: Datacenter for the hosts, CALs for
     * the terminal server and SQL, the software of the departments. ESET
     * runs out soon - a warning on the dashboard. Microsoft 365 the agent
     * reports.
     */
    private function licences(): void
    {
        foreach ([
            'Windows Server 2019 Datacenter',
            'Windows Server 2022 Standard',
            'Windows Server 2025 Datacenter',
            'Windows Server 2025 Datacenter',
            'Windows Server 2025 Datacenter',
            'Windows 10 Pro',
        ] as $i => $os) {
            $this->make(LicenseWindows::class, [
                'operating_system_id' => $this->os($os),
                'key' => implode('-', str_split($this->serial('win-'.$i.$os, 25), 5)),
            ]);
        }

        foreach (['RDS User CAL 2019 (20)', 'Windows Server 2025 User CAL (25)', 'SQL Server 2022 Standard CAL (10)'] as $name) {
            $this->make(LicenseAccess::class, ['name' => $name, 'key' => implode('-', str_split($this->serial('cal-'.$name, 25), 5))]);
        }

        foreach ([
            ['Microsoft 365 Business Premium (16)', 'admin@mustermann.onmicrosoft.com', now()->subMonths(8), now()->addMonths(4), 'jährlich', 'm365:sku:SPB'],
            ['Autodesk Inventor Professional (2)', 'tobias.wagner@mustermann.de', now()->subMonths(5), now()->addMonths(7), 'jährlich', null],
            ['AutoCAD LT (1)', 'sandra.hoffmann@mustermann.de', now()->subMonths(5), now()->addMonths(7), 'jährlich', null],
            ['abas ERP Wartung', 'erika.mustermann@mustermann.de', now()->subYear(), now()->addMonths(11), 'jährlich', null],
            ['ESET PROTECT Entry (25)', 'it@mustermann.de', now()->subYear()->addWeeks(5), now()->addWeeks(5), 'jährlich', null],
            ['Veeam Data Platform Foundation (6 Instanzen)', 'it@mustermann.de', now()->subMonths(9), now()->addMonths(3), 'jährlich', null],
            ['DATEV Unternehmen online', 'erika.mustermann@mustermann.de', now()->subYears(2), now()->addYear(), 'monatlich', null],
        ] as [$name, $user, $from, $to, $abo, $agentId]) {
            $this->make(LicenseSoftware::class, [
                'name' => $name, 'username' => $user, 'key' => implode('-', str_split($this->serial('sw-'.$name, 20), 5)),
                'start_date' => $from->toDateString(), 'end_date' => $to->toDateString(), 'abo' => $abo, 'agent_identifier' => $agentId,
            ]);
        }
    }

    /**
     * Mail runs in Exchange Online. The shared and function mailboxes the
     * Microsoft 365 agent reports; one old IMAP box at IONOS of the former
     * domain is kept by hand for the archive.
     */
    private function mail(): void
    {
        $m365 = MailboxProvider::where('name', 'Microsoft 365 / Exchange Online')->value('id');
        $ionos = MailboxProvider::where('name', 'IONOS')->value('id');

        foreach ([
            ['Info', 'info@mustermann.de'], ['Rechnungseingang', 'rechnung@mustermann.de'], ['Bewerbungen', 'bewerbung@mustermann.de'],
            ['Einkauf', 'einkauf@mustermann.de'], ['Scan', 'scan@mustermann.de'], ['Frank Zimmermann (ausgeschieden)', 'frank.zimmermann@mustermann.de'],
        ] as [$name, $address]) {
            $this->make(Mailbox::class, [
                'mailbox_provider_id' => $m365, 'name' => $name, 'mailAdress' => $address, 'username' => $address,
                'password' => 'Box!'.ucfirst(strtok($address, '@')).'-26', 'agent_identifier' => 'm365:'.$this->guid('mbx:'.$address),
            ]);
        }

        $this->make(Mailbox::class, [
            'mailbox_provider_id' => $ionos, 'name' => 'Altes Postfach (mustermann-gmbh.de)', 'mailAdress' => 'info@mustermann-gmbh.de',
            'username' => 'info@mustermann-gmbh.de', 'password' => 'Ionos!Alt-Postfach-19',
        ]);
    }

    private function domainsAndCertificates(): void
    {
        $this->make(Domain::class, [
            'name' => 'mustermann.de', 'registrar' => 'IONOS', 'registry_registrar' => 'IONOS SE', 'expiry_date' => now()->addMonths(4)->toDateString(),
            'nameserver1' => 'ns1081.ui-dns.de', 'nameserver2' => 'ns1081.ui-dns.com', 'mx' => 'mustermann-de.mail.protection.outlook.com',
            'spf' => 'v=spf1 include:spf.protection.outlook.com -all', 'dmarc' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@mustermann.de',
            'auto_check' => false, 'notes' => 'Hauptdomain. Verlängerung automatisch über das IONOS-Kundenkonto.',
        ]);
        $this->make(Domain::class, [
            'name' => 'mustermann-gmbh.de', 'registrar' => 'IONOS', 'registry_registrar' => 'IONOS SE', 'expiry_date' => now()->addYear()->toDateString(),
            'nameserver1' => 'ns1081.ui-dns.de', 'nameserver2' => 'ns1081.ui-dns.com', 'auto_check' => false,
            'notes' => 'Alte Domain, leitet auf mustermann.de weiter.',
        ]);

        foreach ([
            ['Wildcard *.mustermann.de', '*.mustermann.de', "Let's Encrypt", 'Wildcard', now()->subMonths(2), now()->addWeeks(3), 'Erneuerung per DNS-Challenge auf CT-Proxy'],
            ['remote.mustermann.de', 'remote.mustermann.de', 'Sectigo', 'SSL/TLS', now()->subMonths(3), now()->addMonths(9), 'RD-Gateway auf SRV-RDS01'],
            ['uma.mustermann.de', 'uma.mustermann.de', "Let's Encrypt", 'SSL/TLS', now()->subWeeks(4), now()->addWeeks(8), null],
        ] as [$name, $cn, $issuer, $type, $from, $to, $notes]) {
            $this->make(Certificate::class, [
                'name' => $name, 'common_name' => $cn, 'issuer' => $issuer, 'type' => $type,
                'issued_date' => $from->toDateString(), 'expiry_date' => $to->toDateString(), 'auto_check' => false, 'notes' => $notes,
            ]);
        }
    }

    private function servicesOutside(): void
    {
        $own = $this->make(FTPServer::class, ['host' => 'ftp.mustermann.de', 'description' => 'FTPS für Zeichnungen mit Kunden, läuft auf CT-Proxy']);
        $taxes = $this->make(FTPServer::class, ['host' => 'upload.steuerbuero-lange.de', 'description' => 'Belegupload an das Steuerbüro']);

        foreach (['kunde-nordtec', 'kunde-meyer-stahlbau'] as $account) {
            $this->link($own, $this->make(LoginGeneral::class, [
                'name' => 'FTP '.$account, 'description' => 'Zeichnungsaustausch', 'username' => $account, 'password' => 'Ftp!'.$account.'-26',
            ]));
        }
        $this->link($taxes, $this->make(LoginGeneral::class, [
            'name' => 'FTP Steuerbüro Lange', 'description' => 'Monatliche Belege', 'username' => 'mustermann', 'password' => 'Ftp!Lange-Belege-26',
        ]));

        $this->make(DynDNS::class, ['host' => 'mustermann-muc', 'domain' => 'spdns.de', 'providor' => 'SPDYN (Securepoint)', 'username' => 'mustermann-muc', 'password' => 'Dyn!Filiale-2026']);
    }

    /**
     * The backup plan. Veeam saves the Hyper-V host and is reported by the
     * agent on SRV-BAK01 (its runs and last night's failure come from
     * DemoAgentSeeder); the cloud copy of the NAS is documented by hand.
     */
    private function backups(): void
    {
        $this->make(Backup::class, [
            'agent_identifier' => 'veeam:VMs-taeglich', 'name' => 'Veeam – Hyper-V täglich', 'software' => 'Veeam Backup & Replication 12',
            'source' => 'SRV-HV01 (SRV-DC01, SRV-FS01, SRV-RDS01, SRV-ERP01)', 'destination' => 'NAS-Backup', 'schedule' => 'täglich 22:00',
            'retention' => '30 Wiederherstellungspunkte', 'password' => 'Veeam!Encrypt-2026',
            'notes' => 'Verschlüsselte Sicherung, Kopie per Hyper Backup in die Cloud.',
        ]);
        $this->make(Backup::class, [
            'name' => 'Cloud-Kopie NAS-Backup', 'software' => 'Synology Hyper Backup', 'source' => 'NAS-Backup (Veeam-Repository)',
            'destination' => 'Wasabi Cloud (eu-central-2)', 'schedule' => 'täglich 05:00', 'retention' => '90 Tage, unveränderlich',
            'last_success' => now()->subDay()->toDateString(), 'password' => 'Wasabi!Copy-2026',
        ]);
    }

    /**
     * The network drives of the file server, each with an AD group for
     * reading and one for writing; the onboarding form offers all but Scans.
     * The Windows agent reports the shares of SRV-FS01.
     */
    private function shares(): void
    {
        $fileServer = $this->device(VM::class, 'SRV-FS01');

        foreach ([
            ['Gemeinsam', 'S', 'Gemeinsam', false, true, 'Für alle Mitarbeiter'],
            ['Vorlagen', 'V', 'Vorlagen', false, true, 'Briefpapier, Formulare, Angebotsvorlagen'],
            ['Projekte', 'P', 'Projekte', false, true, 'Kundenaufträge mit Zeichnungen'],
            ['Buchhaltung', 'B', 'Buchhaltung', true, true, 'Nur Buchhaltung und Geschäftsführung'],
            ['Geschäftsführung', 'G', 'GF', true, true, 'Verträge, Personal'],
            ['Scans', 'X', 'Scans', false, false, 'Ziel der Scanner'],
        ] as [$name, $letter, $folder, $sensitive, $offered, $description]) {
            $groups = collect(['R' => 'lesen', 'RW' => 'ändern'])->map(fn ($wording, $suffix) => ADGroup::firstOrCreate(
                ['customer_id' => $this->customer->id, 'name' => "DL_{$folder}_{$suffix}"],
                ['description' => "Fileserver \\\\SRV-FS01\\{$folder} – {$wording}", 'agent_identifier' => $this->guid('adg:DL_'.$folder.$suffix)],
            ));

            $this->make(NetworkShare::class, [
                'host_type' => VM::class, 'host_id' => $fileServer?->id, 'name' => $name, 'drive_letter' => $letter,
                'path' => "\\\\SRV-FS01\\{$folder}", 'description' => $description,
                'ad_group_read_id' => $groups['R']->id, 'ad_group_write_id' => $groups['RW']->id,
                'sensitive' => $sensitive, 'in_onboarding_form' => $offered,
                'agent_identifier' => 'srv-fs01:'.mb_strtolower($folder),
            ]);
        }

        // Members of the drive groups by department - as the AD agent reports them.
        $members = fn (string $group, array $from) => ADGroup::where('customer_id', $this->customer->id)->where('name', $group)->first()
            ?->users()->syncWithoutDetaching(
                ADGroup::where('customer_id', $this->customer->id)->whereIn('name', $from)->with('users')->get()->flatMap->users->pluck('id')->unique()->values()
            );
        $members('DL_Gemeinsam_RW', ['M365-Standard']);
        $members('DL_Vorlagen_R', ['M365-Standard']);
        $members('DL_Vorlagen_RW', ['GG_Geschäftsführung']);
        $members('DL_Projekte_RW', ['GG_Konstruktion', 'GG_Fertigung', 'GG_Vertrieb']);
        $members('DL_Projekte_R', ['GG_Geschäftsführung', 'GG_Einkauf']);
        $members('DL_Buchhaltung_RW', ['GG_Buchhaltung']);
        $members('DL_GF_RW', ['GG_Geschäftsführung']);
        $members('DL_Scans_R', ['GG_Buchhaltung']);
        ADGroup::where('customer_id', $this->customer->id)->where('name', 'DL_Scans_RW')->first()
            ?->users()->syncWithoutDetaching(ADUser::where('customer_id', $this->customer->id)->where('username', 'svc-scan')->pluck('id'));
    }

    /**
     * The onboarding options, and one checklist each way: a new designer
     * starting soon, half prepared - and the offboarding of Frank
     * Zimmermann, done.
     */
    private function checklists(): void
    {
        foreach ([
            ['app', 'Microsoft 365', 'M365-Standard', 'Postfach, Teams, Office', 'it', false],
            ['app', 'VPN', 'VPN-Benutzer', 'Securepoint SSL-VPN', 'it', true],
            ['app', 'Terminalserver', 'RDS-Benutzer', 'Anmeldung an SRV-RDS01 (Fertigung, Lager)', 'it', false],
            ['app', 'abas ERP', null, 'Benutzer im ERP anlegen lassen', 'customer', false],
            ['app', 'DATEV', null, 'beantragt die Buchhaltung selbst', 'customer', true],
            ['app', 'Autodesk Inventor', null, 'Lizenz zuweisen (nur Konstruktion)', 'it', true],
        ] as $sort => [$kind, $name, $group, $detail, $assignee, $approval]) {
            ChecklistOption::create([
                'customer_id' => $this->customer->id, 'kind' => $kind, 'name' => $name,
                'ad_group_id' => $group ? ADGroup::where('customer_id', $this->customer->id)->where('name', $group)->value('id') : null,
                'detail' => $detail, 'assignee' => $assignee, 'needs_approval' => $approval, 'sort' => $sort,
            ]);
        }

        $builder = app(ChecklistBuilder::class);
        $shares = ChecklistBuilder::onboardingShares($this->customer)->keyBy('name');
        $options = ChecklistOption::where('customer_id', $this->customer->id)->whereIn('name', ['Microsoft 365', 'VPN', 'Autodesk Inventor'])->pluck('id')->all();
        $drives = [];
        foreach (['Gemeinsam' => 'write', 'Vorlagen' => 'read', 'Projekte' => 'write'] as $share => $level) {
            if ($id = $shares->get($share)?->id) {
                $drives[$id] = $level;
            }
        }

        $onboarding = $builder->startOnboarding($this->customer, [
            'first_name' => 'Lena', 'last_name' => 'Vogel', 'reference_date' => now()->addDays(10)->toDateString(),
            'note' => 'Neue Konstrukteurin, bekommt WS-KONSTR-03 (bestellt).', 'drives' => $drives, 'options' => $options,
        ], null);
        // Half prepared: the first steps are done.
        $onboarding->items()->orderBy('sort')->limit(3)->get()->each(fn ($item) => $item->update(['done_at' => now()->subDays(2)]));
        $onboarding->update(['position' => 'Konstruktion', 'supervisor' => 'Tobias Wagner']);

        $frank = ADUser::where('customer_id', $this->customer->id)->where('username', 'frank.zimmermann')->first();
        if ($frank) {
            $offboarding = $builder->startOffboarding($this->customer, $frank, now()->subWeeks(5)->toDateString(), null, 'Postfach noch drei Monate als freigegebenes Postfach behalten.');
            $offboarding->items()->update(['done_at' => now()->subWeeks(5)]);
            $offboarding->update(['completed_at' => now()->subWeeks(5)]);
        }
    }

    /** Two documents of the customer under Dateien. */
    private function files(): void
    {
        foreach ([
            ['Wartungsvertrag 2026', 'Wartungsvertrag_2026.pdf', 'Wartungsvertrag Mustermann - IT-Betreuung 2026'],
            ['Netzplan Zentrale Hamburg', 'Netzplan_Hamburg.pdf', 'Netzplan Zentrale Hamburg - VLAN-Übersicht'],
        ] as [$name, $file, $text]) {
            $path = $this->customer->slug.'/files/'.$file;
            $pdf = $this->pdf($text);
            Storage::disk('local')->put($path, $pdf);
            File::forceCreate([
                'customer_id' => $this->customer->id, 'name' => $name, 'extension' => 'pdf',
                'size' => strlen($pdf), 'file_path' => $path,
            ]);
        }
    }

    /** A minimal, valid one-page PDF with a line of text. */
    private function pdf(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) iconv('UTF-8', 'Windows-1252//TRANSLIT', $text));
        $stream = "BT /F1 14 Tf 72 760 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
