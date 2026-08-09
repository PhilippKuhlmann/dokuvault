<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IpAddress extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $table = 'ip_addresses';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function ipable()
    {
        return $this->morphTo();
    }

    public function network()
    {
        return $this->belongsTo(Network::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
