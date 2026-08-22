<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Verknüpft einen Login-Eintrag mit einem dokumentierten Objekt.
 *
 * Bewusst ein eigenes Model statt morphToMany: So lässt sich zu einem Login
 * generisch aufzählen, wo es überall verwendet wird - mit morphToMany bräuchte
 * es dafür eine Relation je Gerätetyp.
 *
 * Kein SoftDeletes: Eine Verknüpfung ist keine Dokumentation, sondern ein
 * Verweis. Login und Gerät liegen bei Bedarf im Papierkorb.
 */
class CredentialLink extends Model
{
    use HasFactory;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function login()
    {
        return $this->belongsTo(LoginGeneral::class, 'login_general_id');
    }

    public function credentialable()
    {
        return $this->morphTo();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** URL-Slug des Zieltyps - zugleich der Schlüssel in config('custom.trashables'). */
    public function zielSlug(): string
    {
        return strtolower(class_basename($this->credentialable_type));
    }

    /**
     * Wie das Ziel in einer Liste heißt: "SRV-01 (Server)".
     *
     * Telefone und DECT-Geräte haben keine Namensspalte - dort tritt die IP an
     * die Stelle des Namens, sonst stünde da nur die Typbezeichnung.
     */
    public function zielBezeichnung(): string
    {
        $ziel = $this->credentialable;

        if (! $ziel) {
            return '';
        }

        $typ = config('custom.trashables')[$this->zielSlug()][1] ?? class_basename($ziel);
        $name = $ziel->name ?? $ziel->ip ?? $ziel->ip1 ?? '#'.$ziel->id;

        return $name.' ('.__($typ).')';
    }

    /**
     * Die Verknuepfung selbst hat keinen Namen - sie traegt den des Geraets,
     * an dem die Zugangsdaten haengen.
     */
    public function protokollName(): ?string
    {
        return $this->zielBezeichnung() ?: null;
    }
}
