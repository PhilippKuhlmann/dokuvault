<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ein Server-Cluster: welche Server zusammengehoeren und mit welcher Technik
 * (Ceph, Replikation ...).
 *
 * Nicht auf Proxmox beschraenkt - der Typ sagt, worum es geht, damit auch ein
 * Hyper-V- oder Datenbank-Cluster hier hineinpasst.
 */
class Cluster extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    /**
     * Beim Loeschen verlieren die Server nur ihre Zugehoerigkeit.
     *
     * Sie sind eigene Geraete und bleiben stehen. Die Fremdschluessel-Regel
     * (nullOnDelete) greift erst beim endgueltigen Loeschen - der Cluster
     * wandert aber zunaechst in den Papierkorb.
     *
     * Steht am Model und nicht am Controller: Geloescht wird im Modal, und ein
     * Cluster ohne diese Regel liesse Server mit einer Zugehoerigkeit zurueck,
     * die es nicht mehr gibt.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $cluster) {
            $cluster->servers()->update(['cluster_id' => null]);
        });
    }

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function servers()
    {
        return $this->hasMany(Server::class)->orderBy('name');
    }

    /**
     * Die Technik ausgeschrieben. Ein unbekannter Schluessel (Typ nachtraeglich
     * aus der Konfiguration entfernt) gibt den Rohwert zurueck statt zu
     * verschwinden - er stand ja einmal bewusst da.
     */
    public function typBezeichnung(): ?string
    {
        if (blank($this->type)) {
            return null;
        }

        return __(config('custom.cluster_types')[$this->type] ?? $this->type);
    }
}
