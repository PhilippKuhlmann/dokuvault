<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AgentToken extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * sha256-Hash für die Speicherung/Suche (kein Klartext in der DB).
     */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Erzeugt einen neuen Token-Datensatz und gibt den Klartext-Token zurück
     * (wird nur einmalig angezeigt).
     */
    public static function generateFor(Customer $customer, Site $site, ?string $name = null): array
    {
        $plain = 'doc_'.Str::random(48);

        $token = static::create([
            'customer_id' => $customer->id,
            'site_id' => $site->id,
            'name' => $name,
            'token' => static::hashToken($plain),
        ]);

        return [$token, $plain];
    }
}
