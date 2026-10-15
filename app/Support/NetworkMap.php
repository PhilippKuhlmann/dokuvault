<?php

namespace App\Support;

use App\Models\Accesspoint;
use App\Models\Camera;
use App\Models\Computer;
use App\Models\Customer;
use App\Models\DECT;
use App\Models\Firewall;
use App\Models\InternetConnection;
use App\Models\IoTDevice;
use App\Models\IpAddress;
use App\Models\Machine;
use App\Models\NAS;
use App\Models\Network;
use App\Models\NetworkSwitch;
use App\Models\OtherClient;
use App\Models\Phone;
use App\Models\PhoneSystem;
use App\Models\Printer;
use App\Models\Recorder;
use App\Models\Router;
use App\Models\Scanner;
use App\Models\SecurepointUMA;
use App\Models\Server;
use App\Models\Site;
use App\Models\Ups;
use App\Models\VM;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * The logical network map of a customer, one per site:
 * internet connection -> gateway device -> VLANs -> devices.
 *
 * Everything comes from data that is already documented - no cabling. A VLAN
 * hangs below the device that owns its gateway address, a device below the
 * VLAN its address lies in. What cannot be placed is listed separately
 * instead of being dropped, so the map also shows gaps in the documentation.
 */
class NetworkMap
{
    /** Device class => label, in the order the groups appear inside a VLAN. */
    public const TYPES = [
        Firewall::class => 'Firewall',
        Router::class => 'Router',
        NetworkSwitch::class => 'Switch',
        Accesspoint::class => 'Accesspoint',
        Server::class => 'Server',
        VM::class => 'VM',
        NAS::class => 'NAS',
        Ups::class => 'USV',
        Computer::class => 'Computer',
        Printer::class => 'Drucker',
        Scanner::class => 'Scanner',
        PhoneSystem::class => 'TK-Anlage',
        Phone::class => 'Telefon',
        DECT::class => 'DECT',
        Camera::class => 'Kamera',
        Recorder::class => 'Recorder',
        IoTDevice::class => 'IoT-Gerät',
        Machine::class => 'Maschine',
        SecurepointUMA::class => 'E-Mail-Archivierung',
        OtherClient::class => 'Sonstige',
    ];

    /** Devices that stand in the gateway row even without a VLAN below them. */
    protected const EDGE_TYPES = [Firewall::class, Router::class];

    protected Customer $customer;

    /** Device class => list route prefix or null, resolved once per request. */
    protected array $lists = [];

    /** @var Collection<int, Network> */
    protected Collection $networks;

    /** @var Collection<int, IpAddress> */
    protected Collection $addresses;

    /** network id => [first long, last long] */
    protected array $ranges = [];

    public function build(Customer $customer, ?int $siteId = null): array
    {
        $this->customer = $customer;

        $sites = $customer->sites()
            ->when($siteId, fn ($query) => $query->whereKey($siteId))
            ->orderBy('name')
            ->get();

        $this->networks = Network::where('customer_id', $customer->id)->orderBy('vlanId')->get();

        foreach ($this->networks as $network) {
            if ($range = $network->bereich()) {
                $this->ranges[$network->id] = $range;
            }
        }

        // Addresses of all sites: a device elsewhere with an address in this
        // VLAN still sits in it - the same rule as in IPAM.
        $this->addresses = IpAddress::where('customer_id', $customer->id)
            ->with('ipable')
            ->get()
            ->filter(fn (IpAddress $ip) => $ip->ipable && isset(self::TYPES[$ip->ipable::class]))
            ->each(fn (IpAddress $ip) => $ip->setAttribute('map_network_id', $this->networkFor($ip)));

        $edges = collect(self::EDGE_TYPES)
            ->flatMap(fn (string $class) => $class::where('customer_id', $customer->id)->orderBy('name')->get());

        $uplinks = InternetConnection::where('customer_id', $customer->id)->get();

        return $sites
            ->map(fn (Site $site) => $this->site($site, $edges, $uplinks))
            ->all();
    }

    protected function site(Site $site, Collection $edges, Collection $uplinks): array
    {
        $columns = [];

        foreach ($edges->where('site_id', $site->id) as $device) {
            $columns[$this->key($device)] = $this->column($this->node($device));
        }

        // VLANs below the device owning their gateway address. The rest share
        // one column: a column per unknown address grew the map sideways
        // without saying more - each VLAN card names its gateway anyway.
        foreach ($this->networks->where('site_id', $site->id) as $network) {
            $owner = $this->gatewayOwner($network);

            if ($owner) {
                $key = $this->key($owner);
                $columns[$key] ??= $this->column($this->node($owner));
            } else {
                $key = 'gateway:unknown';
                $columns[$key] ??= $this->column(null);
            }

            $columns[$key]['networks'][] = [
                'network' => $network,
                'groups' => $this->groups($network, $owner),
            ];
        }

        $unlinked = [];

        foreach ($uplinks->where('site_id', $site->id) as $uplink) {
            $key = $this->uplinkColumn($uplink, $columns);
            $node = $this->uplinkNode($uplink);

            if ($key) {
                $columns[$key]['uplinks'][] = $node;
            } else {
                $unlinked[] = $node;
            }
        }

        // Documented devices first, in the order they were found (firewalls
        // before routers), then the gateways nobody documented.
        uksort($columns, fn ($a, $b) => str_starts_with($a, 'gateway:') <=> str_starts_with($b, 'gateway:'));

        // A firewall or router with nothing below or above it is listed, not
        // drawn: an empty column per spare device pushed the actual map aside.
        [$columns, $standalone] = collect($columns)
            ->partition(fn (array $column) => $column['networks'] || $column['uplinks']);

        return [
            'site' => $site,
            'columns' => $columns->values()->all(),
            'standalone' => $standalone->pluck('gateway')->values()->all(),
            'unlinkedUplinks' => $unlinked,
            'unassigned' => $this->unassigned($site),
        ];
    }

    protected function column(?array $gateway): array
    {
        return ['gateway' => $gateway, 'uplinks' => [], 'networks' => []];
    }

    /**
     * The network an address belongs to: the one stored at the address, else
     * the first VLAN whose range contains it, preferring the device's site.
     */
    protected function networkFor(IpAddress $ip): ?int
    {
        if ($ip->network_id && $this->networks->contains('id', $ip->network_id)) {
            return $ip->network_id;
        }

        if (! filter_var($ip->address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        $long = ip2long($ip->address) & 0xFFFFFFFF;
        $siteId = $ip->ipable->getAttribute('site_id');

        $matches = $this->networks->filter(fn (Network $network) => isset($this->ranges[$network->id])
            && $long >= $this->ranges[$network->id][0] && $long <= $this->ranges[$network->id][1]);

        return ($matches->firstWhere('site_id', $siteId) ?? $matches->first())?->id;
    }

    protected function gatewayOwner(Network $network): ?Model
    {
        if (! $network->gateway) {
            return null;
        }

        return $this->addresses->first(fn (IpAddress $ip) => ! $ip->istDhcp() && $ip->address === $network->gateway)?->ipable;
    }

    /**
     * The devices in a VLAN, grouped by type. The gateway itself is left out -
     * it already stands above the VLAN.
     */
    protected function groups(Network $network, ?Model $owner): array
    {
        $ownerKey = $owner ? $this->key($owner) : null;
        $order = array_flip(array_keys(self::TYPES));

        return $this->addresses
            ->where('map_network_id', $network->id)
            ->unique(fn (IpAddress $ip) => $this->key($ip->ipable))
            ->reject(fn (IpAddress $ip) => $this->key($ip->ipable) === $ownerKey)
            ->groupBy(fn (IpAddress $ip) => $ip->ipable::class)
            ->sortBy(fn ($ips, string $class) => $order[$class])
            ->map(fn (Collection $ips, string $class) => [
                'label' => self::TYPES[$class],
                'devices' => $ips
                    ->map(fn (IpAddress $ip) => $this->node($ip->ipable) + ['address' => $ip->anzeige()])
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Devices of this site with an address outside every VLAN - most often a
     * VLAN that has not been documented yet.
     */
    protected function unassigned(Site $site): array
    {
        return $this->addresses
            ->whereNull('map_network_id')
            ->filter(fn (IpAddress $ip) => (int) $ip->ipable->getAttribute('site_id') === $site->id)
            ->reject(fn (IpAddress $ip) => in_array($ip->ipable::class, self::EDGE_TYPES, true))
            ->map(fn (IpAddress $ip) => $this->node($ip->ipable) + ['address' => $ip->anzeige()])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * The column an internet connection leads into: the device that holds its
     * WAN address or the provider's gateway, else the only firewall or router
     * of the site. With several and no address match it stays unlinked
     * rather than drawn to a guess.
     */
    protected function uplinkColumn(InternetConnection $uplink, array $columns): ?string
    {
        $candidates = array_filter([$uplink->wan_ip, $uplink->subnet_gateway]);

        foreach ($this->addresses as $ip) {
            if (in_array($ip->address, $candidates, true) && isset($columns[$this->key($ip->ipable)])) {
                return $this->key($ip->ipable);
            }
        }

        $edges = array_filter(
            array_keys($columns),
            fn (string $key) => in_array(strtok($key, ':'), self::EDGE_TYPES, true),
        );

        return count($edges) === 1 ? reset($edges) : null;
    }

    protected function uplinkNode(InternetConnection $uplink): array
    {
        $bandwidth = collect([$uplink->bandwidth_down, $uplink->bandwidth_up])->filter()->implode(' / ');

        return [
            'name' => $uplink->provider ?: __('Internet'),
            'detail' => collect([$uplink->connection_type, $bandwidth ?: null, $uplink->wan_ip])->filter()->implode(' · '),
            'url' => $this->url($uplink),
        ];
    }

    /** @return array{name: string, type: string, url: ?string, detail: ?string} */
    protected function node(Model $device): array
    {
        $model = trim($device->getAttribute('manufacturer').' '.$device->getAttribute('model'));

        return [
            'name' => $device->getAttribute('name') ?: ($model ?: class_basename($device)),
            'type' => self::TYPES[$device::class] ?? class_basename($device),
            'url' => $this->url($device),
            'detail' => $model ?: null,
        ];
    }

    /**
     * Jump to the device's card in its list, where the user may see that
     * list - the same way as from IPAM and the global search.
     */
    protected function url(Model $device): ?string
    {
        $class = $device::class;

        if (! array_key_exists($class, $this->lists)) {
            $type = strtolower(class_basename($class));
            $this->lists[$class] = Route::has($type.'.index') && Gate::allows($type.'_viewAny') ? $type : null;
        }

        return $this->lists[$class]
            ? route($this->lists[$class].'.index', [$this->customer, 'highlight' => $device->getKey()])
            : null;
    }

    protected function key(Model $device): string
    {
        return $device::class.':'.$device->getKey();
    }
}
