<?php

namespace App\Models;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\HatBeschaffung;
use App\Models\Concerns\IstEinbaubar;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhoneSystem extends Model
{
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use HatBeschaffung;
    use IstEinbaubar;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Der Name ist optional - aeltere Anlagen haben nur Hersteller, Art und
     * Modell. Ohne diese Methode stuende eine solche Anlage im Protokoll ohne
     * Namen da.
     */
    public function protokollName(): ?string
    {
        return $this->name ?: (trim($this->manufacturer.' '.($this->model ?: $this->type)) ?: null);
    }

    /** Label in the rack: never empty, even without a name. */
    public function rackName(): string
    {
        return $this->protokollName() ?? '#'.$this->id;
    }
}
