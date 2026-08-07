<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use App\Rules\IstNetz;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InternetConnection extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = [];

    /**
     * Nutzbarer Adressbereich des gerouteten Netzes, z. B.
     * "203.0.113.17 – 203.0.113.30 (14 Adressen)".
     *
     * Nur IPv4: Bei IPv6 ist die Zahl so gross, dass sie niemandem hilft, und
     * es gibt dort weder Netz- noch Broadcast-Adresse im selben Sinn.
     */
    public function nutzbarerBereich(): ?string
    {
        if (blank($this->subnet) || ! str_contains($this->subnet, '/')) {
            return null;
        }

        [$adresse, $praefix] = explode('/', $this->subnet, 2);
        $roh = @inet_pton($adresse);

        if ($roh === false || strlen($roh) !== 4 || ! ctype_digit($praefix) || (int) $praefix > 30) {
            return null;
        }

        $netz = ip2long(inet_ntop(IstNetz::netzadresse($roh, (int) $praefix)));
        $anzahl = 2 ** (32 - (int) $praefix);

        return long2ip($netz + 1).' – '.long2ip($netz + $anzahl - 2).' ('.($anzahl - 2).' '.__('Adressen').')';
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
