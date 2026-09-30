<?php

namespace App\Models;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\HatBeschaffung;
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
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Eine TK-Anlage hat keinen Namen, nur Hersteller, Art und Modell.
     *
     * Ohne diese Methode stuende die Anlage im Protokoll ohne Namen da.
     */
    public function protokollName(): ?string
    {
        return trim($this->manufacturer.' '.($this->model ?: $this->type)) ?: null;
    }
}
