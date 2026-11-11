<?php

namespace App\Http\Controllers\API\Agent;

use App\Http\Controllers\API\Agent\Concerns\ReportsAddresses;
use App\Models\Accesspoint;
use App\Models\Firewall;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\Server;
use App\Models\Wifi;
use Illuminate\Http\Request;

/**
 * The network reports itself: a UniFi controller with its switches, access
 * points and WLANs, and a firewall with its interfaces and DHCP ranges.
 */
class NetworkController extends AgentEndpoint
{
    use ReportsAddresses;

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
            $this->reportNetworkDevice(NetworkSwitch::class, $g, $customer, $site);
            $switches++;
        }

        $accesspoints = 0;
        foreach ($data['accesspoints'] ?? [] as $g) {
            $this->reportNetworkDevice(Accesspoint::class, $g, $customer, $site);
            $accesspoints++;
        }

        $wlans = 0;
        foreach ($data['wifis'] ?? [] as $w) {
            $wlan = Wifi::firstOrNew([
                'customer_id' => $customer->id,
                'site_id' => $site->id,
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
     * A firewall as the firewall agent reports it - OPNsense or Securepoint,
     * in one vendor-neutral shape. The differences between vendors live in
     * the scripts only, so a third vendor needs no change here.
     *
     * Matched by agent_identifier; a firewall documented by hand before is
     * found by its serial number and taken over instead of duplicated. Name,
     * management URL and everything the form keeps (credentials, notes,
     * rack) belong to the user: the name is only set on a new entry, the URL
     * only while empty.
     */
    public function firewall(Request $request)
    {
        $customer = $request->attributes->get('agentCustomer');
        $site = $request->attributes->get('agentSite');

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial' => ['nullable', 'string', 'max:255'],
            'firmware' => ['nullable', 'string', 'max:255'],
            'management_url' => ['nullable', 'string', 'max:255'],
            'subscription_until' => ['nullable', 'date'],
            'interfaces' => ['nullable', 'array', 'max:500'],
            'interfaces.*.name' => ['required', 'string', 'max:255'],
            'interfaces.*.device' => ['nullable', 'string', 'max:255'],
            'interfaces.*.ip' => ['nullable', 'string', 'max:255'],
            'interfaces.*.vlan' => ['nullable', 'integer', 'min:1', 'max:4094'],
            // WAN and the like: a provider's transfer net is no VLAN of ours.
            'interfaces.*.wan' => ['nullable', 'boolean'],
            // DHCP ranges on their own: each goes to the network its start
            // address lies in - the same for every DHCP server a vendor has.
            'dhcp_ranges' => ['nullable', 'array', 'max:500'],
            'dhcp_ranges.*.start' => ['required', 'ipv4'],
            'dhcp_ranges.*.end' => ['required', 'ipv4'],
            // DNS servers DHCP hands out there. Empty: the firewall itself,
            // which is what OPNsense does unless told otherwise.
            'dhcp_ranges.*.dns' => ['nullable', 'array', 'max:8'],
            'dhcp_ranges.*.dns.*' => ['ipv4'],
            'vpns' => ['nullable', 'array', 'max:500'],
            'vpns.*.type' => ['required', 'string', 'max:50'],
            'vpns.*.name' => ['required', 'string', 'max:255'],
            'vpns.*.remote' => ['nullable', 'string', 'max:255'],
            'port_forwards' => ['nullable', 'array', 'max:1000'],
            'port_forwards.*.description' => ['nullable', 'string', 'max:255'],
            'port_forwards.*.interface' => ['nullable', 'string', 'max:255'],
            'port_forwards.*.protocol' => ['nullable', 'string', 'max:50'],
            'port_forwards.*.port' => ['nullable', 'string', 'max:255'],
            'port_forwards.*.target' => ['nullable', 'string', 'max:255'],
            'port_forwards.*.target_port' => ['nullable', 'string', 'max:255'],
            'gateways' => ['nullable', 'array', 'max:100'],
            'gateways.*.name' => ['required', 'string', 'max:255'],
            'gateways.*.address' => ['nullable', 'string', 'max:255'],
        ]);

        $firewall = Firewall::where('customer_id', $customer->id)->where('site_id', $site->id)
            ->where('agent_identifier', $data['identifier'])->first();

        if (! $firewall && filled($data['serial'] ?? null)) {
            $firewall = Firewall::where('customer_id', $customer->id)->where('site_id', $site->id)
                ->whereNull('agent_identifier')
                ->where('serialNumber', $data['serial'])->first();
        }

        $neu = ! $firewall;
        $firewall ??= new Firewall([
            'customer_id' => $customer->id,
            'site_id' => $site->id,
            'name' => $data['name'],
        ]);

        $firewall->agent_identifier = $data['identifier'];

        // What the device says about itself replaces what was typed - but
        // "not reported" is not "empty".
        foreach (['manufacturer' => 'manufacturer', 'model' => 'model', 'serial' => 'serialNumber', 'firmware' => 'firmware', 'subscription_until' => 'subscription_until'] as $feld => $spalte) {
            if (filled($data[$feld] ?? null)) {
                $firewall->$spalte = $data[$feld];
            }
        }

        if (blank($firewall->management_url) && filled($data['management_url'] ?? null)) {
            $firewall->management_url = $data['management_url'];
        }

        $firewall->agent_details = [
            'interfaces' => array_values($data['interfaces'] ?? []),
            'vpns' => array_values($data['vpns'] ?? []),
            'port_forwards' => array_values($data['port_forwards'] ?? []),
            'gateways' => array_values($data['gateways'] ?? []),
        ];
        $firewall->agent_reported_at = now();
        $firewall->save();

        // The internal networks first, so the firewall's own addresses below
        // already find the network they belong to.
        $netze = 0;
        foreach ($data['interfaces'] ?? [] as $schnittstelle) {
            $netze += $this->reportNetwork($schnittstelle, $data['dhcp_ranges'] ?? [], $customer->id, $firewall->site_id) ? 1 : 0;
        }

        // Interface addresses into the IPAM, without the prefix length.
        foreach ($data['interfaces'] ?? [] as $schnittstelle) {
            $this->reportUrl($firewall, $customer->id, $firewall->site_id, strtok((string) ($schnittstelle['ip'] ?? ''), '/') ?: null, false);
        }

        return response()->json([
            'status' => 'ok',
            'customer' => $customer->name,
            'site' => $site->name,
            'firewall' => $firewall->name,
            'created' => $neu,
            'interfaces_documented' => count($data['interfaces'] ?? []),
            'networks_documented' => $netze,
            'vpns_documented' => count($data['vpns'] ?? []),
            'port_forwards_documented' => count($data['port_forwards'] ?? []),
        ]);
    }

    /**
     * An interface of a firewall as network under Netzwerk -> VLAN: the net
     * from its address, the firewall as gateway, VLAN tag and the DHCP range
     * whose start lies in it - with the DNS servers handed out there (the
     * firewall itself unless the range names others).
     *
     * Matched by network address and prefix at the customer and site, so a
     * network entered by hand is completed instead of duplicated. Only empty
     * fields are filled - name, DNS and anything typed in stay. Nothing is
     * ever deleted: a VLAN gone from the firewall stays documented.
     *
     * Skipped: WAN interfaces, IPv6, no address, /31 and /32 (no network
     * worth a VLAN entry).
     */
    protected function reportNetwork(array $schnittstelle, array $dhcpBereiche, int $customerId, ?int $siteId): bool
    {
        if (! empty($schnittstelle['wan']) || ! str_contains((string) ($schnittstelle['ip'] ?? ''), '/')) {
            return false;
        }

        [$adresse, $praefix] = explode('/', $schnittstelle['ip'], 2);

        if (filter_var($adresse, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || ! ctype_digit($praefix) || (int) $praefix < 1 || (int) $praefix > 30) {
            return false;
        }

        $maske = Network::maskeAusCidr((int) $praefix);
        $netzadresse = long2ip(ip2long($adresse) & ip2long($maske));

        $dhcp = collect($dhcpBereiche)->first(
            fn ($b) => (ip2long($b['start']) & ip2long($maske)) === ip2long($netzadresse)
        );

        $netz = Network::where('customer_id', $customerId)
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->where('network', $netzadresse)
            ->where(fn ($q) => $q->where('cidr', $praefix)->orWhere('subnetmask', $maske))
            ->first();

        // 0.0.0.0 in a DHCP option means "this server" - the firewall.
        $dns = $dhcp
            ? collect($dhcp['dns'] ?? [])->map(fn ($a) => $a === '0.0.0.0' ? $adresse : $a)->unique()->values()->all() ?: [$adresse]
            : [];

        $werte = [
            'vlanId' => $schnittstelle['vlan'] ?? null,
            'gateway' => $adresse,
            'dhcpStart' => $dhcp['start'] ?? null,
            'dhcpEnd' => $dhcp['end'] ?? null,
            'dns1' => $dns[0] ?? null,
            'dns2' => $dns[1] ?? null,
            'subnetmask' => $maske,
            'cidr' => $praefix,
        ];

        if (! $netz) {
            Network::create($werte + [
                'customer_id' => $customerId,
                'site_id' => $siteId,
                'description' => $schnittstelle['name'],
                'network' => $netzadresse,
            ]);

            return true;
        }

        foreach ($werte as $feld => $wert) {
            if (blank($netz->$feld) && filled($wert)) {
                $netz->$feld = $wert;
            }
        }
        $netz->save();

        return true;
    }

    /**
     * Legt einen Switch bzw. Accesspoint an oder aktualisiert ihn.
     *
     * @param  class-string  $klasse
     */
    protected function reportNetworkDevice(string $klasse, array $g, $customer, $site): void
    {
        $geraet = $klasse::updateOrCreate(
            ['customer_id' => $customer->id, 'site_id' => $site->id, 'agent_identifier' => $g['identifier']],
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
        $this->reportUrl($geraet, $customer->id, $site->id, $g['ip'] ?? null, $this->bezugsweg($g));
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
}
