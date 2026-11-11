<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\HasProcurement;
use App\Models\Concerns\IsMountable;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ups extends Model
{
    use BelongsToCustomer;
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use HasProcurement;
    use IsMountable;
    use TracksChanges;

    protected $table = 'ups';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
