<?php

namespace App\Models;

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

class Server extends Model
{
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use HasProcurement;
    use IsMountable;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'full_depth' => 'boolean',
        'agent_details' => 'array',
        'agent_reported_at' => 'datetime',
    ];

    /** Only these servers can be mounted into a rack. */
    public function scopeRackMountable($query)
    {
        return $query->where('form_factor', 'rack');
    }

    public function istRackServer(): bool
    {
        return $this->form_factor === 'rack';
    }

    /**
     * Not in responses of the interface.
     *
     * The model goes out as JSON, and the encrypted value used to stand there
     * - useless for the caller and a leak the moment someone turns the
     * attribute into a cast. Whoever needs the password fetches it where it
     * belongs.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'bmcPassword',
        'remotePassword',
    ];

    protected function bmcPassword(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => Crypt::encryptString($value),
        );
    }

    protected function remotePassword(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => Crypt::encryptString($value),
        );
    }

    protected function services(): Attribute
    {
        return new Attribute(
            get: fn ($value) => explode(',', $value),
        );
    }

    public function operatingSystem()
    {
        return $this->belongsTo(OperatingSystem::class);
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    /** The VMs and containers running on this host (VM::host). */
    public function gaeste()
    {
        return $this->hasMany(VM::class, 'server_id');
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
