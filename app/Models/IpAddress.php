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

    /** Was am Geraet steht, wo sonst die Adresse stuende. */
    public const MARKE_DHCP = 'DHCP';

    protected $casts = ['dhcp' => 'boolean'];

    public function istDhcp(): bool
    {
        return (bool) $this->dhcp;
    }

    /**
     * Was am Geraet steht.
     *
     * Bei DHCP nicht die Adresse: Sie stimmt nur bis zum naechsten Neustart,
     * und wer sie abliest, verlaesst sich auf etwas, das morgen woanders
     * steht. Gespeichert bleibt sie trotzdem - der IP-Plan braucht sie, um das
     * Geraet dem richtigen Pool zuzuordnen.
     */
    public function anzeige(): string
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
