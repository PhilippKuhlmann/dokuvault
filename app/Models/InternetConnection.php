<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use App\Rules\IstNetz;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class InternetConnection extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Bandbreiten als reine Zahl ablegen - die Einheit steht im Formular fest
     * neben dem Feld und in der Anzeige hinter dem Wert. Altbestand wie
     * "1000 Mbit/s" wird beim naechsten Speichern mitgezogen.
     */
    protected function bandwidthDown(): Attribute
    {
        return $this->nurZahl();
    }

    protected function bandwidthUp(): Attribute
    {
        return $this->nurZahl();
    }

    private function nurZahl(): Attribute
    {
        return new Attribute(
            get: fn ($value) => filled($value) ? preg_replace('/\D+/', '', (string) $value) : null,
            set: fn ($value) => filled($value) ? preg_replace('/\D+/', '', (string) $value) : null,
        );
    }

    /**
     * Bandbreite fuer die Anzeige: "250 Mbit/s". Leere Werte bleiben leer,
     * damit die Karten keine nackte Einheit zeigen.
     */
    public function bandbreite(?string $wert): ?string
    {
        return filled($wert) ? $wert.' Mbit/s' : null;
    }

    /**
     * Einwahlpasswort verschluesselt ablegen - wie bei Router, NAS und den
     * uebrigen Geraeten. Ein leerer Wert bleibt leer, sonst stuende der
     * Chiffretext eines Leerstrings in der Spalte.
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
