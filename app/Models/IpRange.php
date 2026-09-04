<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein reservierter Adressbereich innerhalb eines Netzes.
 *
 * Er belegt nichts - er sagt nur, wofuer das Stueck gedacht ist. Im IPAM steht
 * dadurch "reserviert: Proxmox-Server" statt "frei", auch bei Adressen, die
 * noch niemand vergeben hat.
 */
class IpRange extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class);
    }

    /** Die Anfangsadresse als Zahl - so rechnet der IP-Plan. */
    public function vonLong(): ?int
    {
        return self::alsLong($this->from_ip);
    }

    public function bisLong(): ?int
    {
        return self::alsLong($this->to_ip);
    }

    /**
     * Eine IPv4-Adresse als vorzeichenlose Zahl, oder null.
     *
     * Nur IPv4: Der IP-Plan rechnet mit ip2long und zeigt IPv6-Netze ohnehin
     * nicht als Adressliste. Ein Bereich in einem IPv6-Netz waere eine Zeile,
     * die nirgends erscheint.
     */
    public static function alsLong(?string $adresse): ?int
    {
        $adresse = trim((string) $adresse);

        if (! filter_var($adresse, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        return ip2long($adresse) & 0xFFFFFFFF;
    }

    /**
     * Die Bereiche eines Netzes in Lesereihenfolge, jeder mit seiner Farbe.
     *
     * Eine Stelle fuer beides: die Tabelle im Plan und die Liste darunter.
     * Zwei getrennte Zuordnungen wuerden dieselbe Reservierung verschieden
     * einfaerben, sobald eine Sortierung abweicht - und dann waere die Liste
     * keine Legende mehr.
     *
     * Sortiert nach Anfangsadresse, nicht nach Anlagedatum: So wechselt die
     * Farbe von Zeile zu Zeile, und genau darum geht es. Nach from_ip in der
     * Datenbank zu sortieren taugt nicht - dort steht Text, und ".100" kaeme
     * vor ".20".
     */
    public static function eingefaerbt($bereiche)
    {
        $farben = config('custom.ipam_farben', []);

        return collect($bereiche)
            ->sortBy(fn (self $b) => $b->vonLong() ?? PHP_INT_MAX)
            ->values()
            ->each(function (self $b, int $i) use ($farben) {
                $b->farbe = $farben === [] ? [] : $farben[$i % count($farben)];
            });
    }

    /** Wie viele Adressen der Bereich umfasst - 0, wenn er unsinnig ist. */
    public function anzahl(): int
    {
        $von = $this->vonLong();
        $bis = $this->bisLong();

        return ($von === null || $bis === null || $bis < $von) ? 0 : $bis - $von + 1;
    }
}
