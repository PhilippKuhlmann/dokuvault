<?php

namespace App\Models;

use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Computer extends Model
{
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use TracksChanges;

    protected $guarded = [];

    public function operatingSystem()
    {
        return $this->belongsTo(OperatingSystem::class);
    }

    protected function remotePassword(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => Crypt::encryptString($value),
        );
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
