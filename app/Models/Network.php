<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Network extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function wifis()
    {
        return $this->hasMany(Wifi::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    /** Reserved ranges - also the scoped binding of {ipRange} in the app routes. */
    public function ipRanges()
    {
        return $this->hasMany(IpRange::class);
    }

    /**
     * The network for display: description and VLAN number together, because
     * both are needed - the name says what for, the number is what you need
     * at the switch. If one is missing, the other stands on its own.
     *
     * @param  bool  $ohneBeschreibung  the VLAN number only - for the case that
     *                                  the description already stands on the same
     *                                  line as the label
     */
    public function label(bool $ohneBeschreibung = false): ?string
    {
        $teile = [];

        if (! $ohneBeschreibung && filled($this->description)) {
            $teile[] = $this->description;
        }

        if (filled($this->vlanId)) {
            $teile[] = 'VLAN '.$this->vlanId;
        }

        return $teile ? implode(' · ', $teile) : null;
    }

    /**
     * Subnet mask into CIDR notation: 255.255.255.0 becomes 24.
     *
     * Both fields say the same thing, so the application works one out from
     * the other instead of asking for it twice.
     *
     * Demands a real mask - the ones have to run contiguously from the left.
     * 255.0.255.0 is not a mask but a typo, and yields null here instead of
     * an invented number.
     */
    public static function cidrAusMaske(?string $maske): ?int
    {
        $maske = trim((string) $maske);

        if ($maske === '' || filter_var($maske, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }

        $bits = str_pad(decbin(ip2long($maske)), 32, '0', STR_PAD_LEFT);

        // After the last one no further one may follow.
        if (! preg_match('/^1*0*$/', $bits)) {
            return null;
        }

        return substr_count($bits, '1');
    }

    /**
     * The other direction: 24 becomes 255.255.255.0.
     */
    public static function maskeAusCidr(int|string|null $cidr): ?string
    {
        if (! is_numeric($cidr)) {
            return null;
        }

        $cidr = (int) $cidr;

        if ($cidr < 0 || $cidr > 32) {
            return null;
        }

        // /0 separately: a shift by 32 places is undefined and returns
        // nonsense instead of 0.0.0.0, depending on the platform.
        if ($cidr === 0) {
            return '0.0.0.0';
        }

        return long2ip(-1 << (32 - $cidr));
    }

    /**
     * Looks among a site's networks for the one whose address range the given
     * IPv4 address falls into - for the auto-documentation agents, which report
     * an IP but know no VLAN. The first network with a hit wins; with
     * overlapping networks (rare in practice) the result is not unambiguous,
     * but still better than no assignment at all.
     */
    public static function forUrl(int $customerId, ?int $siteId, string $adresse): ?self
    {
        if (filter_var($adresse, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }

        return static::where('customer_id', $customerId)
            ->when($siteId, fn ($query) => $query->where('site_id', $siteId))
            ->get()
            ->first(fn (self $netz) => $netz->containsUrl($adresse));
    }

    /**
     * The first address of this network that can be given to a device for
     * good: neither network nor broadcast address, not the gateway, not in
     * the DHCP range, not reserved and not yet in use at this customer.
     * null when the network is full or cannot be evaluated.
     *
     * A suggestion, not a promise - whoever hands out the address between
     * suggestion and save is caught by the uniqueness check.
     */
    public function nextFreeAddress(): ?string
    {
        $bereich = $this->range();

        if (! $bereich) {
            return null;
        }

        [$netz, $broadcast] = $bereich;
        // /31 and /32 have no network and broadcast address of their own.
        [$erste, $letzte] = $broadcast - $netz >= 3 ? [$netz + 1, $broadcast - 1] : [$netz, $broadcast];

        $belegt = IpAddress::where('customer_id', $this->customer_id)
            ->whereNotNull('address')
            ->pluck('address')
            ->merge(Server::where('customer_id', $this->customer_id)->whereNotNull('bmcIp')->pluck('bmcIp'))
            ->push($this->gateway)
            ->filter(fn ($adresse) => filter_var($adresse, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
            ->mapWithKeys(fn ($adresse) => [ip2long($adresse) & 0xFFFFFFFF => true])
            ->all();

        $gesperrt = IpRange::where('network_id', $this->id)->get()
            ->map(fn (IpRange $r) => [$r->vonLong(), $r->bisLong()])
            ->push($this->dhcpRange())
            ->filter(fn ($b) => $b && $b[0] !== null && $b[1] !== null)
            ->all();

        for ($ip = $erste; $ip <= $letzte; $ip++) {
            if (isset($belegt[$ip])) {
                continue;
            }

            foreach ($gesperrt as [$von, $bis]) {
                if ($ip >= $von && $ip <= $bis) {
                    // Jump straight to the end of the block instead of walking
                    // it address by address - on a /16 that is a difference.
                    $ip = $bis;

                    continue 2;
                }
            }

            return long2ip($ip);
        }

        return null;
    }

    /**
     * The DHCP range as [start, end] in long notation, or null.
     * dhcpStart/dhcpEnd are either full addresses or a host part ("100")
     * that is placed into the network.
     */
    public function dhcpRange(): ?array
    {
        $netz = $this->range()[0] ?? null;

        if ($netz === null) {
            return null;
        }

        $aufloesen = function ($wert) use ($netz): ?int {
            $wert = trim((string) $wert);

            return match (true) {
                $wert === '' => null,
                (bool) filter_var($wert, FILTER_VALIDATE_IP) => ip2long($wert) & 0xFFFFFFFF,
                ctype_digit($wert) => $netz + (int) $wert,
                default => null,
            };
        };

        $start = $aufloesen($this->dhcpStart);
        $ende = $aufloesen($this->dhcpEnd);

        return $start !== null && $ende !== null && $start <= $ende ? [$start, $ende] : null;
    }

    /**
     * Checks whether an IPv4 address falls into this network's address range.
     */
    public function containsUrl(string $adresse): bool
    {
        $bereich = $this->range();

        if (! $bereich || filter_var($adresse, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $lang = ip2long($adresse) & 0xFFFFFFFF;

        return $lang >= $bereich[0] && $lang <= $bereich[1];
    }

    /**
     * Ganzer Adressbereich als [Netzadresse, Broadcast] in long-Schreibweise -
     * including network and broadcast address, so every reported IP can be
     * checked. Unlike the usable range in the IP plan (which excludes network
     * and broadcast address for the occupancy display), the full range counts
     * here - a reported gateway IP, for one, is deliberately included.
     */
    public function range(): ?array
    {
        $base = $this->network ? ip2long($this->network) : false;

        if ($base === false) {
            return null;
        }

        $base &= 0xFFFFFFFF;

        $cidr = null;
        if (is_numeric($this->cidr)) {
            $cidr = (int) $this->cidr;
        } elseif ($this->subnetmask && ($mask = ip2long($this->subnetmask)) !== false) {
            $cidr = substr_count(str_pad(decbin($mask & 0xFFFFFFFF), 32, '0', STR_PAD_LEFT), '1');
        }

        if ($cidr === null || $cidr < 0 || $cidr > 32) {
            return null;
        }

        if ($cidr === 0) {
            return [0, 0xFFFFFFFF];
        }

        $hostCount = 2 ** (32 - $cidr);
        $networkLong = $base & ((0xFFFFFFFF << (32 - $cidr)) & 0xFFFFFFFF);

        return [$networkLong, $networkLong + $hostCount - 1];
    }
}
