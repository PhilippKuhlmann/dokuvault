<?php

namespace App\Models;

use App\Models\Concerns\OwnedByCustomer;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactPerson extends Model
{
    use HasFactory, SoftDeletes;
    use OwnedByCustomer;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * A contact person is named with first and last name.
     *
     * Neither field alone is any use: "Torben" says as little in the log as
     * "Ahlers", and the central list in config/custom.php can take only one
     * field, not two.
     */
    public function activityName(): ?string
    {
        return trim($this->first_name.' '.$this->last_name) ?: null;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
