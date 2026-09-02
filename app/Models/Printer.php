<?php

namespace App\Models;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\HasIpAddresses;
use App\Models\Concerns\HatBeschaffung;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Printer extends Model
{
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use HatBeschaffung;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Das Kennwort des Druckers.
     *
     * Lag bis dahin im Klartext in der Datenbank - als einziges Kennwort dieser Anwendung.
     * Ein Datenbank-Abzug enthielt es damit lesbar.
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn ($wert) => ! empty($wert) ? Crypt::decryptString($wert) : null,
            set: fn ($wert) => ! empty($wert) ? Crypt::encryptString($wert) : null,
        );
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
