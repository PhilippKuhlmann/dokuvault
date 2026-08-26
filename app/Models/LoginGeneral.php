<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class LoginGeneral extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    public const KIND = 'password';

    /** Name des Filters - wer ihn aufhebt, soll nicht raten muessen. */
    public const SCOPE = 'kennwort';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * In der eigenen Liste stehen nur Kennwoerter, keine SSH-Schluessel.
     *
     * SshKey erbt von dieser Klasse und ueberschreibt booted() ohne
     * parent::booted() - dadurch gilt dort der eigene Filter statt diesem.
     * Wer hier etwas ergaenzt, muss es deshalb auch dort tun.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(self::SCOPE, fn (Builder $abfrage) => $abfrage->where('kind', self::KIND));
    }

    protected function password(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => Crypt::encryptString($value),
        );
    }

    /**
     * Fremdschluessel ausdruecklich: SshKey erbt diese Relation, und Eloquent
     * wuerde den Namen sonst aus der Klasse ableiten - "ssh_key_id" gibt es nicht.
     */
    /**
     * Ob dieser Eintrag ein SSH-Schluessel ist.
     *
     * Steht hier und nicht an SshKey: Ueber eine Verknuepfung kommt immer ein
     * LoginGeneral zurueck, auch wenn dahinter ein Schluessel steckt (siehe
     * CredentialLink::login). Die Anzeige am Geraet muss beides unterscheiden.
     */
    public function istSchluessel(): bool
    {
        return $this->kind === SshKey::KIND;
    }

    public function links()
    {
        return $this->hasMany(CredentialLink::class, 'login_general_id');
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
