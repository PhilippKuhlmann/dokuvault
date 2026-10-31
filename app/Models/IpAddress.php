<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpAddress extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $table = 'ip_addresses';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /** What stands on the device where the address would otherwise stand. */
    public const MARKE_DHCP = 'DHCP';

    protected $casts = ['dhcp' => 'boolean'];

    public function istDhcp(): bool
    {
        return (bool) $this->dhcp;
    }

    /**
     * What stands on the device.
     *
     * With DHCP not the address: it only holds until the next restart, and
     * whoever reads it off relies on something that stands elsewhere
     * tomorrow. It is stored all the same - the IP plan needs it to assign
     * the device to the right pool.
     */
    public function label(): string
    {
        return $this->istDhcp() ? __(self::MARKE_DHCP) : (string) $this->address;
    }

    public function ipable()
    {
        return $this->morphTo();
    }

    public function network()
    {
        return $this->belongsTo(Network::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
