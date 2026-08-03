<?php

namespace App\Models;

use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Server extends Model
{
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use TracksChanges;

    protected $guarded = [];

    protected $casts = [
        'full_depth' => 'boolean',
    ];

    /** Nur diese Server lassen sich in einen Serverschrank einbauen. */
    public function scopeRackMountable($query)
    {
        return $query->where('form_factor', 'rack');
    }

    public function istRackServer(): bool
    {
        return $this->form_factor === 'rack';
    }

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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
