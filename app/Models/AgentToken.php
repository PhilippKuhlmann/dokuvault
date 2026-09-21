<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AgentToken extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Abgelaufen ist ein Token nur, wenn er eine Frist hat und sie vorbei ist.
     *
     * Kein Ablaufdatum bedeutet Altbestand - vor Einfuehrung der Pflicht-Frist
     * angelegt. Solche Token bleiben gueltig, sonst waeren mit der Migration
     * alle laufenden Agenten stehengeblieben.
     */
    public function istAbgelaufen(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

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
    public static function generateFor(Customer $customer, Site $site, ?string $name = null, ?DateTimeInterface $expiresAt = null): array
    {
        $plain = 'doc_'.Str::random(48);

        $token = static::create([
            'customer_id' => $customer->id,
            'site_id' => $site->id,
            'name' => $name,
            'token' => static::hashToken($plain),
            'expires_at' => $expiresAt,
        ]);

        return [$token, $plain];
    }

    /**
     * Erneuert den Token an Ort und Stelle: neuer Klartext, neue Frist, der
     * alte Wert ist ab sofort ungültig. Kunde, Standort und Name bleiben - so
     * kann ein verbrannter Token gewechselt werden, ohne die Zuordnung und den
     * Protokoll-Verlauf zu verlieren.
     *
     * last_used_at wird zurückgesetzt: Die Angabe gehört zum alten Wert, der
     * neue wurde noch nie benutzt.
     */
    public function erneuern(DateTimeInterface $expiresAt): string
    {
        $plain = 'doc_'.Str::random(48);

        $this->forceFill([
            'token' => static::hashToken($plain),
            'expires_at' => $expiresAt,
            'last_used_at' => null,
        ])->save();

        return $plain;
    }
}
