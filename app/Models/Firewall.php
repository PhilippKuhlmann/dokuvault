<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\HasProcurement;
use App\Models\Concerns\IsMountable;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

/**
 * The vendor-independent firewall - Sophos, Fortigate, OPNsense, pfSense.
 *
 * The Securepoint UTM stays an object of its own: it has fields that exist
 * only there (USC PIN, cloud backup password, three separate interfaces).
 * This class is for everything else.
 */
class Firewall extends Model
{
    use BelongsToCustomer;
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use HasProcurement;
    use IsMountable;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'subscription_until' => 'date',
        'agent_details' => 'array',
        'agent_reported_at' => 'datetime',
    ];

    /**
     * The USC PIN unlocks the console, the cloud backup password the backup -
     * both belong in the table encrypted.
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
     * Securepoint devices have four fields that no other vendor has. The
     * vendor is free text, so it is compared rather than tested for equality:
     * "Securepoint GmbH" is the same thing.
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
