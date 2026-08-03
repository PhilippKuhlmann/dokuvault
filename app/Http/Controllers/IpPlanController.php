<?php

namespace App\Http\Controllers;

use App\Models\Accesspoint;
use App\Models\Camera;
use App\Models\Computer;
use App\Models\Customer;
use App\Models\DECT;
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
use App\Models\SecurepointUMA;
use App\Models\SecurepointUTM;
use App\Models\Server;
use App\Models\Ups;
use App\Models\VM;

class IpPlanController extends Controller
{
    /**
     * Geräte-Modelle mit ihren IP-Spalten. Sekundäre Spalten bekommen ein Suffix am Label.
     * [Model, [spalte => label-suffix]]
     */
    protected const IP_SOURCES = [
        [Server::class, ['ip1' => '', 'ip2' => ' (IP2)', 'bmcIp' => ' (BMC)']],
        [VM::class, ['ip1' => '', 'ip2' => ' (IP2)']],
        [NAS::class, ['ip1' => '', 'ip2' => ' (IP2)']],
        [PhoneSystem::class, ['ip1' => '']],
        [Computer::class, ['ip' => '']],
        [Printer::class, ['ip' => '']],
        [Camera::class, ['ip' => '']],
        [Recorder::class, ['ip' => '']],
        [Phone::class, ['ip' => '']],
        [DECT::class, ['ip' => '']],
        [NetworkSwitch::class, ['ip' => '']],
        [Accesspoint::class, ['ip' => '']],
        [Router::class, ['ip' => '']],
        [IoTDevice::class, ['ip' => '']],
        [OtherClient::class, ['ip' => '']],
        [Ups::class, ['ip' => '']],
        [Machine::class, ['ip' => '']],
        [SecurepointUTM::class, ['ip' => '']],
        [SecurepointUMA::class, ['ip' => '']],
    ];

    // Obergrenze an Host-Adressen, die vollständig aufgelistet werden (schützt vor riesigen Subnetzen).
    protected const MAX_HOSTS = 8192;

    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Network::class);

        // Nur die VLANs des gewählten Standorts anzeigen.
        $networks = $this->getFilteredQuery(Network::class, $customer)
            ->orderBy('vlanId')
            ->get();

        // Die belegten Adressen bewusst OHNE Standortfilter einsammeln: liegt ein Gerät
        // eines anderen Standorts in diesem Netz, muss es sichtbar bleiben - sonst würde
        // eine tatsächlich vergebene Adresse hier als frei erscheinen.
        $used = $this->collectUsedIps($customer);

        $plans = $networks->map(function (Network $network) use ($used) {
            return [
                'network' => $network,
                'plan' => $this->buildPlan($network, $used),
            ];
        });

        $totalUsed = $plans->sum(fn ($entry) => $entry['plan']['usedCount'] ?? 0);
        $totalAddresses = $plans->sum(fn ($entry) => $entry['plan']['total'] ?? 0);

        return view('ipplan.index', compact('customer', 'plans', 'totalUsed', 'totalAddresses'));
    }

    /**
     * Alle im Kunden vergebenen IP-Adressen einsammeln: [ip_long => Gerätename].
     */
    protected function collectUsedIps(Customer $customer): array
    {
        $used = [];

        $addLong = function (int $long, string $label) use (&$used) {
            $used[$long] = ($used[$long] ?? '') === '' ? $label : $used[$long].' / '.$label;
        };

        foreach (self::IP_SOURCES as [$class, $columns]) {
            $rows = $class::where('customer_id', $customer->id)->get();

            foreach ($rows as $row) {
                $name = $row->getAttribute('name')
                    ?: trim(($row->getAttribute('manufacturer') ?? '').' '.($row->getAttribute('model') ?? ''))
                    ?: ('#'.$row->id);
                foreach ($columns as $column => $suffix) {
                    $value = $row->getAttribute($column);
                    if (! $value || ! filter_var($value, FILTER_VALIDATE_IP)) {
                        continue;
                    }
                    $addLong(ip2long($value) & 0xFFFFFFFF, $name.$suffix);
                }
            }
        }

        // Zusätzliche (polymorphe) IP-Adressen — z. B. Gateway-IPs eines Routers je VLAN
        IpAddress::where('customer_id', $customer->id)
            ->with('ipable')
            ->get()
            ->each(function ($ip) use ($addLong) {
                if (! filter_var($ip->address, FILTER_VALIDATE_IP)) {
                    return;
                }
                $deviceName = $ip->ipable?->getAttribute('name')
                    ?: ($ip->ipable ? class_basename($ip->ipable) : 'Gerät');
                $label = $deviceName.($ip->label ? ' ('.$ip->label.')' : '');
                $addLong(ip2long($ip->address) & 0xFFFFFFFF, $label);
            });

        return $used;
    }

    /**
     * Baut die Zeilen für ein VLAN: belegte Adressen einzeln, freie und DHCP-Bereiche zusammengefasst.
     */
    protected function buildPlan(Network $network, array $used): array
    {
        $range = $this->networkRange($network);
        if (! $range) {
            return ['error' => 'Ungültiges Netz/Subnetz', 'rows' => []];
        }

        [$networkLong, $first, $last] = $range;

        $truncated = false;
        if ($last - $first > self::MAX_HOSTS) {
            $last = $first + self::MAX_HOSTS;
            $truncated = true;
        }

        // Nur belegte Adressen innerhalb dieses Subnetzes
        $map = array_filter($used, fn ($k) => $k >= $first && $k <= $last, ARRAY_FILTER_USE_KEY);

        // Gateway markieren
        $gatewayLong = null;
        if ($network->gateway && filter_var($network->gateway, FILTER_VALIDATE_IP)) {
            $gw = ip2long($network->gateway) & 0xFFFFFFFF;
            if ($gw >= $first && $gw <= $last) {
                $map[$gw] = isset($map[$gw]) ? 'Gateway / '.$map[$gw] : 'Gateway';
                $gatewayLong = $gw;
            }
        }

        $dhcp = $this->dhcpRange($network, $networkLong);

        $rows = [];
        $counts = ['device' => 0, 'dhcp' => 0, 'free' => 0];
        $runStart = null;
        $runKind = null;

        $flush = function ($endLong) use (&$rows, &$runStart, &$runKind) {
            if ($runStart === null) {
                return;
            }
            $rows[] = [
                'kind' => $runKind, // 'free' | 'dhcp'
                'from' => long2ip($runStart),
                'to' => long2ip($endLong),
                'single' => $runStart === $endLong,
                'label' => $runKind === 'dhcp' ? 'DHCP-Bereich' : 'frei',
            ];
            $runStart = null;
            $runKind = null;
        };

        for ($ip = $first; $ip <= $last; $ip++) {
            if (isset($map[$ip])) {
                $flush($ip - 1);
                $counts['device']++;
                $rows[] = [
                    'kind' => 'device',
                    'from' => long2ip($ip),
                    'to' => long2ip($ip),
                    'single' => true,
                    'label' => $map[$ip],
                    'isGateway' => $ip === $gatewayLong,
                ];

                continue;
            }

            $kind = ($dhcp && $ip >= $dhcp[0] && $ip <= $dhcp[1]) ? 'dhcp' : 'free';
            $counts[$kind]++;
            if ($runKind !== $kind) {
                $flush($ip - 1);
                $runStart = $ip;
                $runKind = $kind;
            }
        }
        $flush($last);

        return [
            'error' => null,
            'rows' => $rows,
            'counts' => $counts,
            'truncated' => $truncated,
            'total' => $last - $first + 1,
            'usedCount' => count($map),
        ];
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
            // /31 und /32: keine gesonderte Netz-/Broadcast-Adresse
            return [$networkLong, $networkLong, $networkLong + $hostCount - 1];
        }

        return [$networkLong, $networkLong + 1, $networkLong + $hostCount - 2];
    }

    /**
     * @return array{0:int,1:int}|null [startLong, endLong]
     */
    protected function dhcpRange(Network $network, int $networkLong): ?array
    {
        $start = $this->resolveHost($network->dhcpStart, $networkLong);
        $end = $this->resolveHost($network->dhcpEnd, $networkLong);

        if ($start === null || $end === null || $start > $end) {
            return null;
        }

        return [$start, $end];
    }

    protected function resolveHost($value, int $networkLong): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return ip2long($value) & 0xFFFFFFFF;
        }
        // Reiner Host-Offset (z. B. "100") -> in das Subnetz einsetzen
        if (ctype_digit($value)) {
            return $networkLong + (int) $value;
        }

        return null;
    }
}
