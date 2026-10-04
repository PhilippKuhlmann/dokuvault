<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\IpAddress;
use App\Models\IpRange;
use App\Models\Network;
use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class IpPlanController extends Controller
{
    /**
     * Geräte-Modelle mit ihren verbliebenen IP-Spalten. Die dokumentierten
     * Adressen kommen aus ip_addresses (siehe unten); als Spalte am Gerät gibt
     * es nur noch die BMC-Adresse des Servers.
     * [Model, [spalte => label-suffix]]
     */
    protected const IP_SOURCES = [
        [Server::class, ['bmcIp' => ' (BMC)']],
    ];

    // Obergrenze an Host-Adressen, die vollständig aufgelistet werden (schützt vor riesigen Subnetzen).
    protected const MAX_HOSTS = 8192;

    /** Modellklasse => Listen-URL-Baustein oder null, je Anfrage einmal ermittelt. */
    protected array $listen = [];

    protected Customer $kunde;

    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Network::class);
        $this->kunde = $customer;

        // Nur die VLANs des gewählten Standorts anzeigen.
        $networks = $this->getFilteredQuery(Network::class, $customer)
            ->orderBy('vlanId')
            ->get();

        // Die belegten Adressen bewusst OHNE Standortfilter einsammeln: liegt ein Gerät
        // eines anderen Standorts in diesem Netz, muss es sichtbar bleiben - sonst würde
        // eine tatsächlich vergebene Adresse hier als frei erscheinen.
        $used = $this->collectUsedIps($customer);

        // Geraete ohne feste Adresse: Sie haengen am Netz, nicht an einer Zeile.
        $perDhcp = $this->collectDhcpGeraete($customer);

        // Reservierte Bereiche je Netz. Sie belegen nichts - sie sagen, wofuer
        // ein Stueck gedacht ist, auch wenn davon noch keine Adresse vergeben
        // ist.
        $bereiche = IpRange::where('customer_id', $customer->id)
            ->get()
            ->groupBy('network_id')
            // Je Netz eigene Farben - siehe IpRange::eingefaerbt().
            ->map(fn ($je) => IpRange::eingefaerbt($je));

        $plans = $networks->map(function (Network $network) use ($used, $bereiche, $perDhcp) {
            return [
                'network' => $network,
                'bereiche' => $bereiche->get($network->id, collect()),
                'plan' => $this->buildPlan(
                    $network, $used, $bereiche->get($network->id, collect()), $perDhcp[$network->id] ?? []
                ),
            ];
        });

        $totalUsed = $plans->sum(fn ($entry) => $entry['plan']['usedCount'] ?? 0);
        $totalAddresses = $plans->sum(fn ($entry) => $entry['plan']['total'] ?? 0);

        return view('ipplan.index', compact('customer', 'plans', 'totalUsed', 'totalAddresses'));
    }

    /**
     * Alle im Kunden vergebenen IP-Adressen einsammeln.
     *
     * [ip_long => [Eintrag, ...]], ein Eintrag je Gerät auf dieser Adresse
     * (siehe geraetEintrag). Mehrere Einträge auf einer Adresse sind ein
     * Konflikt und bleiben deshalb alle sichtbar.
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
                    $addLong(ip2long($value) & 0xFFFFFFFF, $this->geraetEintrag($row, $suffix));
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
                $addLong(ip2long($ip->address) & 0xFFFFFFFF,
                    $this->geraetEintrag($ip->ipable, $ip->label ? ' ('.$ip->label.')' : ''));
            });

        return $used;
    }

    /**
     * Die per DHCP versorgten Geräte, je Netz: [network_id => [Eintrag, ...]].
     *
     * Sie haben keine Adresse - was zählt, ist das Netz. Im Plan stehen sie
     * deshalb am DHCP-Bereich und nicht auf einer Zeile, die morgen eine
     * andere wäre.
     */
    protected function collectDhcpGeraete(Customer $customer): array
    {
        $je = [];

        IpAddress::where('customer_id', $customer->id)
            ->where('dhcp', true)
            ->whereNotNull('network_id')
            ->with('ipable')
            ->get()
            ->each(function ($ip) use (&$je) {
                $je[$ip->network_id][] = $this->geraetEintrag($ip->ipable);
            });

        return $je;
    }

    /**
     * Ein Gerät, wie es im Plan steht: Name und - wo der Nutzer die Liste
     * sehen darf - der Sprung dorthin, direkt auf die Karte des Geräts
     * (?highlight=, derselbe Weg wie aus der globalen Suche).
     *
     * @return array{name: string, url: ?string}
     */
    protected function geraetEintrag(?Model $geraet, string $zusatz = ''): array
    {
        if (! $geraet) {
            return ['name' => 'Gerät'.$zusatz, 'url' => null];
        }

        $name = $geraet->getAttribute('name')
            ?: trim(($geraet->getAttribute('manufacturer') ?? '').' '.($geraet->getAttribute('model') ?? ''))
            ?: class_basename($geraet);

        $typ = $this->listenTyp($geraet::class);

        return [
            'name' => $name.$zusatz,
            'url' => $typ
                ? route($typ.'.index', [$this->kunde, 'highlight' => $geraet->id])
                : null,
        ];
    }

    /**
     * Der Objekttyp aus config('forms'), dessen Liste dieses Modell zeigt -
     * null, wenn es keine gibt oder der Nutzer sie nicht sehen darf. Ein
     * Link auf eine verbotene Seite wäre nur ein Umweg zur Fehlermeldung.
     */
    protected function listenTyp(string $klasse): ?string
    {
        if (! array_key_exists($klasse, $this->listen)) {
            $typ = collect(config('forms'))->search(fn ($d) => ($d['model'] ?? null) === $klasse) ?: null;

            $this->listen[$klasse] = $typ && Route::has($typ.'.index') && Gate::allows($typ.'_viewAny')
                ? $typ
                : null;
        }

        return $this->listen[$klasse];
    }

    /**
     * Baut die Zeilen für ein VLAN: belegte Adressen einzeln, freie und DHCP-Bereiche zusammengefasst.
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

        // Nur belegte Adressen innerhalb dieses Subnetzes
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

        $dhcp = $network->dhcpBereich();

        // Je Adresse die Beschriftung des Bereichs, in dem sie liegt. Als
        // Nachschlagetabelle statt einer Schleife je Adresse: Ein /16 haette
        // sonst 65.000 Durchlaeufe mal Anzahl der Bereiche.
        $reserviert = $this->reservierungen($bereiche, $first, $last);

        $rows = [];
        $counts = ['device' => 0, 'dhcp' => 0, 'free' => 0, 'reserved' => 0];

        // Je Bereich getrennt mitzaehlen, damit der Balken oben ihn in seiner
        // eigenen Farbe zeigen kann. Gezaehlt wird nur, was auch als
        // reserviert in der Tabelle steht - eine belegte Adresse im Bereich
        // zaehlt als belegt, sonst kaeme der Balken ueber 100 Prozent.
        $jeBereich = [];
        $runStart = null;
        $runKind = null;

        $runBereich = null;

        // Die Geraete, die aus diesem Netz per DHCP versorgt werden. Sie
        // stehen am Bereich statt an einer Adresse - welche sie gerade haben,
        // ist morgen eine andere. Nur am ersten Bereich: Bei mehreren (eine
        // feste Adresse teilt ihn) stuenden sie sonst doppelt.
        // Genannt werden sie am ERSTEN DHCP-Bereich. flush() laeuft fuer jeden
        // Lauf, auch fuer freie und reservierte - ohne die Pruefung auf die Art
        // stuenden die Geraete am erstbesten Block.
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
                    // Eine belegte Adresse innerhalb einer Reservierung bleibt
                    // eine belegte Adresse - sie traegt nur zusaetzlich, wozu
                    // der Block gedacht ist.
                    'reservierung' => $reserviert[$ip]['label'] ?? null,
                    'farbe' => $reserviert[$ip]['farbe'] ?? [],
                ];

                continue;
            }

            // Reihenfolge: DHCP schlaegt die Reservierung. Ein Bereich, den der
            // DHCP-Server selbst vergibt, ist kein reservierter Block mehr -
            // und wer beides uebereinanderlegt, soll das im Plan sehen.
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

            // Auch bei gleicher Art umbrechen, wenn ein anderer Bereich
            // beginnt - sonst verschmelzen zwei Reservierungen zu einer Zeile
            // mit der Beschriftung und der Farbe der ersten. An der Id, nicht
            // an der Beschriftung: Zwei Bereiche duerfen gleich heissen.
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
            // In Lesereihenfolge, damit der Balken die Farben in derselben
            // Folge zeigt wie die Tabelle darunter.
            'reserviert' => array_values($jeBereich),
            'truncated' => $truncated,
            'total' => $last - $first + 1,
            // Nicht count($map): Eine Adresse, die im Pool aufgegangen ist,
            // zaehlt nicht zusaetzlich als belegt - der Pool steht schon als
            // Ganzes in der Rechnung. Bis auf diesen Fall sind beide Zahlen
            // dieselbe, denn jede belegte Adresse bekam eine eigene Zeile.
            // Ohne gepflegten DHCP-Bereich gibt es keine Zeile, an der sie
            // stehen koennten. Verschwinden duerfen sie trotzdem nicht - dann
            // waere das Geraet in der Doku, aber nicht im Plan.
            'dhcpOhneBereich' => $dhcpGenannt ? [] : $this->ohneDoppelte($dhcpGeraete),
            'usedCount' => $counts['device'],
        ];
    }

    /**
     * Ein Gerät mit mehreren DHCP-Zuordnungen im selben Netz nur einmal nennen.
     */
    protected function ohneDoppelte(array $eintraege): array
    {
        return array_values(collect($eintraege)->unique(fn ($e) => $e['name'].'|'.$e['url'])->all());
    }

    /**
     * Nachschlagetabelle [ip_long => Beschriftung] fuer die reservierten
     * Bereiche eines Netzes, auf den sichtbaren Ausschnitt beschnitten.
     *
     * Ueberlappen zwei Bereiche, gewinnt der zuletzt angelegte. Das Formular
     * laesst Ueberlappungen gar nicht erst zu; die Regel steht hier fuer den
     * Fall, dass doch eine in der Datenbank landet - dann soll der Plan eine
     * Beschriftung zeigen statt zu raten.
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
            // /31 und /32: keine gesonderte Netz-/Broadcast-Adresse
            return [$networkLong, $networkLong, $networkLong + $hostCount - 1];
        }

        return [$networkLong, $networkLong + 1, $networkLong + $hostCount - 2];
    }
}
