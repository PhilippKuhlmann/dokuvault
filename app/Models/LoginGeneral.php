<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class LoginGeneral extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected function password(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => Crypt::encryptString($value),
        );
    }

    public function links()
    {
        return $this->hasMany(CredentialLink::class);
    }

    /**
     * Verknüpfungen zu Objekten, die es noch gibt.
     *
     * Ein Gerät im Papierkorb behält seine Verknüpfung - beim Wiederherstellen
     * ist sie wieder da -, taucht hier aber nicht auf.
     */
    public function verwendungen()
    {
        // Vorgeladene Relation benutzen, wenn es sie gibt - in der Liste haengt
        // sonst je Zeile eine eigene Abfrage dran.
        $links = $this->relationLoaded('links')
            ? $this->links
            : $this->links()->with('credentialable')->get();

        return $links->filter(fn ($link) => $link->credentialable !== null)->values();
    }

    /**
     * Einzeiler für Liste und PDF: "SRV-01 (Server), VM-02 (VM)".
     *
     * Sortiert, weil das polymorphe Vorladen nach Typ gruppiert und die Liste
     * sonst je nach Aufruf eine andere Reihenfolge hätte.
     */
    public function verwendetBei(): string
    {
        return $this->verwendungen()->map->zielBezeichnung()->sort()->implode(', ');
    }
}
