<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpRange;
use App\Models\Network;
use App\Models\Server;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The IP plan of a customer: per network the used, free, DHCP and reserved
 * addresses. One computation for the browser page (IpPlanController) and the
 * iOS app (Mobile\NetworkController).
 *
 * How a device is named differs - the browser links to its list, the app
 * needs type and id - so the caller passes $eintrag: fn (?Model, string
 * $zusatz): array with at least "name".
 */
class IpPlan
{
    /**
     * Device models with their remaining IP columns. The documented
     * Addresses come from ip_addresses (see below); as a column on the
     * device there is only the server's BMC address left.
     * [Model, [spalte => label-suffix]]
     */
    protected const IP_SOURCES = [
        [Server::class, ['bmcIp' => ' (BMC)']],
    ];

    // Upper bound of host addresses listed in full (guards against huge subnets).
    public const MAX_HOSTS = 8192;

    public function __construct(private Closure $eintrag) {}

    /**
     * The plan of every given network: [network, bereiche, plan] each.
     *
     * $networks are the ones to show (the site filter is the caller's); the
     * used addresses are collected for the whole customer.
     */
    public function plans(Customer $customer, Collection $networks): Collection
    {
        // The taken addresses deliberately collected WITHOUT a site filter: if a
        // device of another site sits in this network it must stay visible - an
        // address actually in use would otherwise appear free here.
        $used = $this->collectUsedIps($customer);

        // Devices without a fixed address: they hang on the network, not on a row.
        $perDhcp = $this->collectDhcpDevices($customer);

        // Reserved ranges per network. They take nothing - they say what a
        // piece is meant for, even when no address from it is in use yet.
        //
        $bereiche = IpRange::where('customer_id', $customer->id)
            ->get()
            ->groupBy('network_id')
        // Own colours per network - see IpRange::eingefaerbt().
            ->map(fn ($je) => IpRange::eingefaerbt($je));

        return $networks->map(fn (Network $network) => [
            'network' => $network,
            'bereiche' => $bereiche->get($network->id, collect()),
            'plan' => $this->buildPlan(
                $network, $used, $bereiche->get($network->id, collect()), $perDhcp[$network->id] ?? []
            ),
        ]);
    }

    /**
     * The name of a device in the plan - the same for both callers.
     */
    public static function deviceName(?Model $geraet): string
    {
        if (! $geraet) {
            return 'Gerät';
        }

        return $geraet->getAttribute('name')
            ?: trim(($geraet->getAttribute('manufacturer') ?? '').' '.($geraet->getAttribute('model') ?? ''))
            ?: class_basename($geraet);
    }

    /**
     * Collect every IP address in use at this customer.
     *
     * [ip_long => [entry, ...]], one entry per device on that address.
     * Several entries on one address are a conflict and therefore all
     * stay visible.
     */
    protected function collectUsedIps(Customer $customer): array
    {
        $used = [];

        $addLong = function (int $long, array $eintrag) use (&$used) {
            $used[$long][] = $eintrag;
        };

        foreach (self::IP_SOURCES as [$class, $columns]) {
            $rows = $class::where('customer_id', $customer->id)->get();

            foreach ($rows as $row) {
                foreach ($columns as $column => $suffix) {
                    $value = $row->getAttribute($column);
                    if (! $value || ! filter_var($value, FILTER_VALIDATE_IP)) {
                        continue;
                    }
                    $addLong(ip2long($value) & 0xFFFFFFFF, ($this->eintrag)($row, $suffix));
                }
            }
        }

        // Additional (polymorphic) IP addresses - e.g. gateway IPs of a router per VLAN
        IpAddress::where('customer_id', $customer->id)
            ->with('ipable')
            ->get()
            ->each(function ($ip) use ($addLong) {
                if (! filter_var($ip->address, FILTER_VALIDATE_IP)) {
                    return;
                }
                $addLong(ip2long($ip->address) & 0xFFFFFFFF,
                    ($this->eintrag)($ip->ipable, $ip->label ? ' ('.$ip->label.')' : ''));
            });

        return $used;
    }

    /**
     * The devices served by DHCP, per network: [network_id => [entry, ...]].
     *
     * They have no address - what counts is the network. In the plan they
     * therefore sit on the DHCP range and not on a row that would be a
     * different one tomorrow.
     */
    protected function collectDhcpDevices(Customer $customer): array
    {
        $je = [];

        IpAddress::where('customer_id', $customer->id)
            ->where('dhcp', true)
            ->whereNotNull('network_id')
            ->with('ipable')
            ->get()
            ->each(function ($ip) use (&$je) {
                $je[$ip->network_id][] = ($this->eintrag)($ip->ipable, '');
            });

        return $je;
    }

    /**
     * Builds the rows for a VLAN: taken addresses one by one, free and DHCP ranges merged.
     */
    protected function buildPlan(Network $network, array $used, $bereiche = null, array $dhcpGeraete = []): array
    {
        $range = $this->networkRange($network);
        if (! $range) {
            return ['error' => 'Ungültiges Netz/Subnetz', 'rows' => []];
        }

        [, $first, $last] = $range;

        $truncated = false;
        if ($last - $first > self::MAX_HOSTS) {
            $last = $first + self::MAX_HOSTS;
            $truncated = true;
        }

        // Only taken addresses inside this subnet
        $map = array_filter($used, fn ($k) => $k >= $first && $k <= $last, ARRAY_FILTER_USE_KEY);

        // Gateway markieren
        $gatewayLong = null;
        if ($network->gateway && filter_var($network->gateway, FILTER_VALIDATE_IP)) {
            $gw = ip2long($network->gateway) & 0xFFFFFFFF;
            if ($gw >= $first && $gw <= $last) {
                $map[$gw] ??= [];
                $gatewayLong = $gw;
            }
        }

        $dhcp = $network->dhcpRange();

        // Per address the label of the range it lies in. As a lookup table
        // instead of a loop per address: a /16 would otherwise mean 65,000
        // passes times the number of ranges.
        $reserviert = $this->reservierungen($bereiche, $first, $last);

        $rows = [];
        $counts = ['device' => 0, 'dhcp' => 0, 'free' => 0, 'reserved' => 0];

        // Counted separately per range so the bar above can show it in its
        // own colour. Counted is only what the table holds as reserved - a
        // taken address inside the range counts as taken, otherwise the bar
        // would go past 100 percent.
        $jeBereich = [];
        $runStart = null;
        $runKind = null;

        $runBereich = null;

        // The devices served from this network by DHCP. They sit on the
        // range instead of on an address - whichever they hold now is a
        // different one tomorrow. Only on the first range: with several (a
        // fixed address splits it) they would otherwise appear twice.
        // They are named on the FIRST DHCP range. flush() runs for every
        // run, for free and reserved ones too - without the check on the
        // kind the devices would sit on whichever block came first.
        $dhcpGenannt = false;

        // Devices with a fixed address inside the DHCP range: they stand at
        // the range with their address and a mark instead of cutting it in two.
        // An agent reports the address of a VM but cannot tell whether it
        // came from DHCP - the pool showed as ".150-.165, test, .167-.199",
        // as if someone had set up two ranges.
        $runImPool = [];

        $flush = function ($endLong) use (&$rows, &$runStart, &$runKind, &$runBereich, $dhcpGeraete, &$dhcpGenannt, &$runImPool) {
            if ($runStart === null) {
                return;
            }

            $geraete = [];
            if ($runKind === 'dhcp' && ! $dhcpGenannt) {
                $geraete = $this->ohneDoppelte($dhcpGeraete);
                $dhcpGenannt = true;
            }
            if ($runKind === 'dhcp') {
                $geraete = array_merge($geraete, $runImPool);
            }
            $runImPool = [];

            $rows[] = [
                'kind' => $runKind, // 'free' | 'dhcp' | 'reserved'
                'from' => long2ip($runStart),
                'to' => long2ip($endLong),
                'single' => $runStart === $endLong,
                'label' => match ($runKind) {
                    'dhcp' => 'DHCP-Bereich',
                    'reserved' => $runBereich['label'] ?? '',
                    default => 'frei',
                },
                'farbe' => $runKind === 'reserved' ? ($runBereich['farbe'] ?? []) : [],
                'geraete' => $geraete,
            ];
            $runStart = null;
            $runKind = null;
            $runBereich = null;
        };

        for ($ip = $first; $ip <= $last; $ip++) {
            $imPool = $dhcp && $ip >= $dhcp[0] && $ip <= $dhcp[1] && $ip !== $gatewayLong;

            if (isset($map[$ip]) && $imPool) {
                // Remembered for the range row; the address itself counts as
                // part of the pool below, like every other one in it.
                // With the full address and marked: a fixed address inside the
                // pool may be a conflict - it must stay visible, only it no
                // longer cuts the range in two. Set to "per DHCP" on the
                // address, it moves to the range without the mark.
                foreach ($map[$ip] as $eintrag) {
                    $runImPool[] = ['name' => $eintrag['name'].' ('.long2ip($ip).')', 'imPool' => true] + $eintrag;
                }
            } elseif (isset($map[$ip])) {
                $flush($ip - 1);
                $counts['device']++;
                $rows[] = [
                    'kind' => 'device',
                    'from' => long2ip($ip),
                    'to' => long2ip($ip),
                    'single' => true,
                    'label' => implode(' / ', array_merge(
                        $ip === $gatewayLong ? ['Gateway'] : [],
                        array_column($map[$ip], 'name'),
                    )),
                    'geraete' => $map[$ip],
                    'isGateway' => $ip === $gatewayLong,
                    // A taken address inside a reservation stays a taken
                    // address - it merely carries in addition what the
                    // block is meant for.
                    'reservierung' => $reserviert[$ip]['label'] ?? null,
                    'farbe' => $reserviert[$ip]['farbe'] ?? [],
                ];

                continue;
            }

            // Order: DHCP beats the reservation. A range the DHCP server
            // hands out itself is no reserved block any more - and whoever
            // lays both on top of each other should see that in the plan.
            if ($dhcp && $ip >= $dhcp[0] && $ip <= $dhcp[1]) {
                $kind = 'dhcp';
                $label = null;
            } elseif (isset($reserviert[$ip])) {
                $kind = 'reserved';
                $bereich = $reserviert[$ip];
            } else {
                $kind = 'free';
                $bereich = null;
            }

            $counts[$kind]++;

            if ($kind === 'reserved') {
                $jeBereich[$bereich['id']] ??= ['farbe' => $bereich['farbe'], 'label' => $bereich['label'], 'anzahl' => 0];
                $jeBereich[$bereich['id']]['anzahl']++;
            }

            // Break even within the same kind when another range begins -
            // otherwise two reservations merge into one row with the label
            // and colour of the first. By id, not by label: two ranges are
            // allowed to share a name.
            if ($runKind !== $kind
                || ($kind === 'reserved' && ($runBereich['id'] ?? null) !== $bereich['id'])) {
                $flush($ip - 1);
                $runStart = $ip;
                $runKind = $kind;
                $runBereich = $bereich;
            }
        }
        $flush($last);

        return [
            'error' => null,
            'rows' => $rows,
            'counts' => $counts,
            // In reading order, so the bar shows the colours in the same
            // sequence as the table below it.
            'reserviert' => array_values($jeBereich),
            'truncated' => $truncated,
            'total' => $last - $first + 1,
            // Without a documented DHCP range there is no row for them to
            // sit on. They must not vanish all the same - the device would
            // then be in the documentation but not in the plan.
            'dhcpOhneBereich' => $dhcpGenannt ? [] : $this->ohneDoppelte($dhcpGeraete),
            // Not count($map): an address absorbed into the pool does not
            // count as taken on top - the pool is already in the sum as a
            // whole. Apart from that case both numbers are the same, since
            // every taken address got a row of its own.
            'usedCount' => $counts['device'],
        ];
    }

    /**
     * Name a device with several DHCP assignments in the same network only once.
     * Recognised by the whole entry - the browser carries a URL, the app kind and id.
     */
    protected function ohneDoppelte(array $eintraege): array
    {
        return array_values(collect($eintraege)->unique(fn ($e) => json_encode($e))->all());
    }

    /**
     * Lookup table [ip_long => label] for the reserved ranges of a network,
     * trimmed to the visible section.
     *
     * If two ranges overlap, the one created last wins. The form does not
     * allow overlaps in the first place; the rule stands here for the case
     * that one lands in the database anyway - then the plan should show a
     * label instead of guessing.
     */
    protected function reservierungen($bereiche, int $first, int $last): array
    {
        $treffer = [];

        foreach ($bereiche ?? [] as $bereich) {
            $von = $bereich->vonLong();
            $bis = $bereich->bisLong();

            if ($von === null || $bis === null || $bis < $von) {
                continue;
            }

            $von = max($von, $first);
            $bis = min($bis, $last);

            for ($ip = $von; $ip <= $bis; $ip++) {
                $treffer[$ip] = [
                    'id' => $bereich->id,
                    'label' => $bereich->label,
                    'farbe' => $bereich->farbe ?? [],
                ];
            }
        }

        return $treffer;
    }

    /**
     * @return array{0:int,1:int,2:int}|null [networkLong, firstHost, lastHost]
     */
    protected function networkRange(Network $network): ?array
    {
        $base = $network->network ? ip2long($network->network) : false;
        if ($base === false) {
            return null;
        }
        $base &= 0xFFFFFFFF;

        $cidr = null;
        if (is_numeric($network->cidr)) {
            $cidr = (int) $network->cidr;
        } elseif ($network->subnetmask && ($mask = ip2long($network->subnetmask)) !== false) {
            $cidr = substr_count(str_pad(decbin($mask & 0xFFFFFFFF), 32, '0', STR_PAD_LEFT), '1');
        }

        if (! $cidr || $cidr < 1 || $cidr > 32) {
            return null;
        }

        $hostCount = 2 ** (32 - $cidr);
        $networkLong = $cidr === 0 ? 0 : ($base & ((0xFFFFFFFF << (32 - $cidr)) & 0xFFFFFFFF));

        if ($cidr >= 31) {
            // /31 and /32: no separate network or broadcast address
            return [$networkLong, $networkLong, $networkLong + $hostCount - 1];
        }

        return [$networkLong, $networkLong + 1, $networkLong + $hostCount - 2];
    }
}
