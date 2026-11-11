<?php

namespace App\Models;

use App\Models\Concerns\OwnedByCustomer;
use App\Models\Concerns\TracksChanges;
use App\Rules\IsNetwork;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class InternetConnection extends Model
{
    use HasFactory, SoftDeletes;
    use OwnedByCustomer;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Store bandwidths as a plain number - the unit stands fixed beside the
     * field in the form and behind the value in the display. Legacy data like
     * "1000 Mbit/s" is carried over on the next save.
     */
    protected function bandwidthDown(): Attribute
    {
        return $this->onlyNumber();
    }

    protected function bandwidthUp(): Attribute
    {
        return $this->onlyNumber();
    }

    private function onlyNumber(): Attribute
    {
        return new Attribute(
            get: fn ($value) => filled($value) ? preg_replace('/\D+/', '', (string) $value) : null,
            set: fn ($value) => filled($value) ? preg_replace('/\D+/', '', (string) $value) : null,
        );
    }

    /**
     * Bandwidth for display: "250 Mbit/s". Empty values stay empty so the
     * cards do not show a bare unit.
     */
    public function bandwidth(?string $wert): ?string
    {
        return filled($wert) ? $wert.' Mbit/s' : null;
    }

    /**
     * Store the dial-in password encrypted - as with router, NAS and the
     * other devices. An empty value stays empty, or the ciphertext of an
     * empty string would sit in the column.
     */
    protected function pppoePassword(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => filled($value) ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Nutzbarer Adressbereich des gerouteten Netzes, z. B.
     * "203.0.113.17 – 203.0.113.30 (14 Adressen)".
     *
     * IPv4 only: with IPv6 the number is so large that it helps nobody, and
     * there is neither a network nor a broadcast address in the same sense.
     */
    public function usableRange(): ?string
    {
        if (blank($this->subnet) || ! str_contains($this->subnet, '/')) {
            return null;
        }

        [$adresse, $praefix] = explode('/', $this->subnet, 2);
        $roh = @inet_pton($adresse);

        if ($roh === false || strlen($roh) !== 4 || ! ctype_digit($praefix) || (int) $praefix > 30) {
            return null;
        }

        $netz = ip2long(inet_ntop(IsNetwork::networkAddress($roh, (int) $praefix)));
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
