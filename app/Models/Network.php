<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Network extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function wifis()
    {
        return $this->hasMany(Wifi::class);
    }

    /**
     * Netz fuer die Anzeige: Beschreibung und VLAN-Nummer zusammen, weil beides
     * gebraucht wird - der Name sagt wofuer, die Nummer braucht man am Switch.
     * Fehlt eines, bleibt das andere stehen.
     *
     * @param  bool  $ohneBeschreibung  nur die VLAN-Nummer - fuer den Fall, dass
     *                                  die Beschreibung auf derselben Zeile schon
     *                                  als Bezeichnung steht
     */
    public function anzeige(bool $ohneBeschreibung = false): ?string
    {
        $teile = [];

        if (! $ohneBeschreibung && filled($this->description)) {
            $teile[] = $this->description;
        }

        if (filled($this->vlanId)) {
            $teile[] = 'VLAN '.$this->vlanId;
        }

        return $teile ? implode(' · ', $teile) : null;
    }
}
