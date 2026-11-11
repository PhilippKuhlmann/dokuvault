<?php

namespace App\Models;

use App\Models\Concerns\OwnedByCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reserved address range inside a network.
 *
 * It takes nothing - it only says what the piece is meant for. In the IPAM
 * that reads "reserved: Proxmox servers" instead of "free", even for
 * addresses nobody has handed out yet.
 */
class IpRange extends Model
{
    use HasFactory;
    use OwnedByCustomer;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class);
    }

    /** The start address as a number - that is how the IP plan computes. */
    public function vonLong(): ?int
    {
        return self::alsLong($this->from_ip);
    }

    public function bisLong(): ?int
    {
        return self::alsLong($this->to_ip);
    }

    /**
     * An IPv4 address as an unsigned number, or null.
     *
     * IPv4 only: the IP plan computes with ip2long and does not show IPv6
     * networks as an address list anyway. A range in an IPv6 network would be
     * a row that appears nowhere.
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
     * The ranges of a network in reading order, each with its colour.
     *
     * One place for both: the table in the plan and the list below it. Two
     * separate mappings would colour the same reservation differently as soon
     * as a sort order differed - and then the list would be no legend any
     * more.
     *
     * Sorted by start address, not by creation date: that way the colour
     * changes from row to row, and that is the whole point. Sorting by from_ip
     * in the database is no use - that holds text, and ".100" would come
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

    /** How many addresses the range covers - 0 when it makes no sense. */
    public function anzahl(): int
    {
        $von = $this->vonLong();
        $bis = $this->bisLong();

        return ($von === null || $bis === null || $bis < $von) ? 0 : $bis - $von + 1;
    }
}
