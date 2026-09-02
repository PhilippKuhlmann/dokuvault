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

class Accesspoint extends Model
{
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use HasIpAddresses;
    use HatBeschaffung;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Nicht in Antworten der Schnittstelle.
     *
     * Das Model geht als JSON hinaus, und dort stand bisher der
     * verschluesselte Wert - nutzlos fuer den Aufrufer und ein Leck in
     * dem Moment, in dem jemand aus dem Attribut einen Cast macht.
     * Wer das Kennwort braucht, holt es dort, wo es hingehoert.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    protected function password(): Attribute
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
