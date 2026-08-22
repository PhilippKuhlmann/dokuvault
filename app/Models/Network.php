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

    /**
     * Netz fuer die Anzeige: Beschreibung und VLAN-Nummer zusammen, weil beides
     * gebraucht wird - der Name sagt wofuer, die Nummer braucht man am Switch.
     * Fehlt eines, bleibt das andere stehen.
     *
     * @param  bool  $ohneBeschreibung  nur die VLAN-Nummer - fuer den Fall, dass
     *                                  die Beschreibung auf derselben Zeile schon
     *                                  als Bezeichnung steht
     */
    public function anzeige(bool $ohneBeschreibung = false): ?string
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
     * Subnetzmaske in die CIDR-Schreibweise: 255.255.255.0 wird zu 24.
     *
     * Beide Felder sagen dasselbe aus, deshalb rechnet die Anwendung das eine
     * aus dem anderen aus, statt es zweimal abzufragen.
     *
     * Verlangt eine echte Maske - die Einsen muessen zusammenhaengend von
     * links stehen. 255.0.255.0 ist keine Maske, sondern ein Tippfehler, und
     * ergibt hier null statt einer erfundenen Zahl.
     */
    public static function cidrAusMaske(?string $maske): ?int
    {
        $maske = trim((string) $maske);

        if ($maske === '' || filter_var($maske, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }

        $bits = str_pad(decbin(ip2long($maske)), 32, '0', STR_PAD_LEFT);

        // Nach dem letzten Einser darf keiner mehr kommen.
        if (! preg_match('/^1*0*$/', $bits)) {
            return null;
        }

        return substr_count($bits, '1');
    }

    /**
     * Die Gegenrichtung: 24 wird zu 255.255.255.0.
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

        // /0 gesondert: Ein Schub um 32 Stellen ist nicht definiert und
        // liefert je nach Plattform Unsinn statt 0.0.0.0.
        if ($cidr === 0) {
            return '0.0.0.0';
        }

        return long2ip(-1 << (32 - $cidr));
    }

    /**
     * Sucht unter den Netzen eines Standorts dasjenige, in dessen Adressbereich
     * die gegebene IPv4-Adresse fällt - für die Auto-Dokumentation-Agenten, die
     * eine IP melden, aber kein VLAN kennen. Erstes Netz mit Treffer gewinnt;
     * bei sich überlappenden Netzen (in der Praxis selten) ist das Ergebnis
     * nicht eindeutig, aber immer noch besser als gar keine Zuordnung.
     */
    public static function fuerAdresse(int $customerId, ?int $siteId, string $adresse): ?self
    {
        if (filter_var($adresse, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return null;
        }

        return static::where('customer_id', $customerId)
            ->when($siteId, fn ($query) => $query->where('site_id', $siteId))
            ->get()
            ->first(fn (self $netz) => $netz->enthaeltAdresse($adresse));
    }

    /**
     * Prüft, ob eine IPv4-Adresse in den Adressbereich dieses Netzes fällt.
     */
    public function enthaeltAdresse(string $adresse): bool
    {
        $bereich = $this->bereich();

        if (! $bereich || filter_var($adresse, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $lang = ip2long($adresse) & 0xFFFFFFFF;

        return $lang >= $bereich[0] && $lang <= $bereich[1];
    }

    /**
     * Ganzer Adressbereich als [Netzadresse, Broadcast] in long-Schreibweise -
     * inklusive Netz- und Broadcast-Adresse, damit sich jede gemeldete IP
     * prüfen lässt. Anders als der Nutzbereich im IP-Plan (der Netz- und
     * Broadcast-Adresse für die Belegungs-Anzeige ausschließt), zaehlt hier
     * der volle Bereich - eine gemeldete Gateway-IP z. B. ist bewusst mit drin.
     */
    public function bereich(): ?array
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
