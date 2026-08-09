<?php

namespace App\Models;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class VM extends Model
{
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use TracksChanges;

    protected $table = 'vms';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected function services(): Attribute
    {
        return new Attribute(
            get: fn ($value) => explode(',', $value),
        );
    }

    protected function remotePassword(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => Crypt::encryptString($value),
        );
    }

    public function operatingSystem()
    {
        return $this->belongsTo(OperatingSystem::class);
    }

    public function host()
    {
        return $this->belongsTo(Server::class, 'server_id');
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
