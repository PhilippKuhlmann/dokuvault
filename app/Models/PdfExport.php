<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Auftrag fuer die PDF-Ausgabe eines Kunden.
 *
 * Kein Inventar-Objekt, daher kein SoftDelete und kein Papierkorb: Ein
 * abgeholtes oder abgelaufenes PDF verschwindet einfach.
 */
class PdfExport extends Model
{
    /** Aufgeraeumt wird nach dieser Zeit - das PDF enthaelt alle Zugangsdaten. */
    public const AUFBEWAHRUNG_STUNDEN = 24;

    public const OFFEN = 'offen';

    public const LAEUFT = 'laeuft';

    public const FERTIG = 'fertig';

    public const FEHLER = 'fehler';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'finished_at' => 'datetime',
        'size' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function istFertig(): bool
    {
        return $this->status === self::FERTIG;
    }

    public function laeuftNoch(): bool
    {
        return in_array($this->status, [self::OFFEN, self::LAEUFT], true);
    }

    /**
     * Wartet der Auftrag schon zu lange, ohne dass ihn jemand abarbeitet?
     *
     * Fehlt die Cron-Zeile auf dem Server, bleibt er fuer immer auf "offen"
     * liegen - dann soll die Anzeige das sagen statt endlos zu drehen.
     */
    public function haengt(): bool
    {
        return $this->laeuftNoch() && $this->created_at?->lt(now()->subMinutes(5));
    }

    public function groesseLesbar(): ?string
    {
        return $this->size ? number_format($this->size / 1048576, 1, ',', '.').' MB' : null;
    }
}
