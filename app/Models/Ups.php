<?php

namespace App\Models;

use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\IstEinbaubar;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ups extends Model
{
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use IstEinbaubar;
    use TracksChanges;

    protected $table = 'ups';

    protected $guarded = [];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
