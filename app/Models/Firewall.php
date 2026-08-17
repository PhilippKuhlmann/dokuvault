<?php

namespace App\Models;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\HatBeschaffung;
use App\Models\Concerns\IstEinbaubar;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

/**
 * Die herstellerunabhaengige Firewall - Sophos, Fortigate, OPNsense, pfSense.
 *
 * Die Securepoint UTM bleibt als eigenes Objekt bestehen: Sie hat Felder, die
 * es nur dort gibt (USC-PIN, Cloud-Backup-Kennwort, drei getrennte Oberflaechen).
 * Diese Klasse ist fuer alles andere.
 */
class Firewall extends Model
{
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use HatBeschaffung;
    use IstEinbaubar;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'subscription_until' => 'date',
    ];

    protected function password(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => ! empty($value) ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Die USC-PIN entsperrt die Konsole, das Cloud-Backup-Kennwort die
     * Sicherung - beides gehoert verschluesselt in die Tabelle.
     */
    protected function uscPin(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => ! empty($value) ? Crypt::encryptString($value) : null,
        );
    }

    protected function cloudBackupPassword(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => ! empty($value) ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Securepoint-Geraete haben vier Felder, die es bei keinem anderen
     * Hersteller gibt. Der Hersteller ist Freitext, deshalb wird verglichen und
     * nicht auf Gleichheit geprueft: "Securepoint GmbH" ist dasselbe.
     */
    public function istSecurepoint(): bool
    {
        return str_contains(strtolower((string) $this->manufacturer), 'securepoint');
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
