<?php

namespace App\Models;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\HasProcurement;
use App\Models\Concerns\IsMountable;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhoneSystem extends Model
{
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use HasProcurement;
    use IsMountable;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The name is optional - older systems have only vendor, kind and model.
     * Without this method such a system would stand in the log without
     * Namen da.
     */
    public function activityName(): ?string
    {
        return $this->name ?: (trim($this->manufacturer.' '.($this->model ?: $this->type)) ?: null);
    }

    /** Label in the rack: never empty, even without a name. */
    public function rackName(): string
    {
        return $this->activityName() ?? '#'.$this->id;
    }
}
