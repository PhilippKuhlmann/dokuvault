<?php

namespace App\Models;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\HatBeschaffung;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Scanner extends Model
{
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use HatBeschaffung;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Wohin dieser Scanner scannt. Viele zu viele: Dasselbe Ziel ist oft auf
     * mehreren Geraeten eingerichtet.
     */
    public function scanTargets()
    {
        return $this->belongsToMany(ScanTarget::class)->withTimestamps()->orderBy('name')->orderBy('scan_targets.id');
    }
}
