<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ein dokumentiertes Patchfeld. Anders als der gleichnamige Katalog-Platzhalter
 * gehoert es einem Kunden und traegt seine Ports - erst dadurch laesst sich
 * beantworten, wo eine Netzwerkdose haengt.
 */
class PatchPanel extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = [];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function ports()
    {
        return $this->hasMany(PatchPort::class)->orderBy('number');
    }

    /**
     * Legt fehlende Portzeilen an, damit 1..port_count lueckenlos existieren.
     * Vorhandene Zeilen bleiben unangetastet - dort steht die Dokumentation.
     */
    public function syncPorts(): void
    {
        $vorhanden = $this->ports()->pluck('number')->all();

        $fehlende = collect(range(1, $this->port_count))
            ->diff($vorhanden)
            ->map(fn (int $number) => [
                'customer_id' => $this->customer_id,
                'patch_panel_id' => $this->id,
                'number' => $number,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($fehlende) {
            PatchPort::insert($fehlende);
        }

        // Ueberzaehlige Ports entfernen. Der PatchPanelController laesst das
        // Verkleinern nur zu, wenn dort nichts dokumentiert ist.
        $this->ports()->where('number', '>', $this->port_count)->delete();
    }

    /** Portnummern oberhalb von $portCount, in denen etwas dokumentiert ist. */
    public function documentedPortsAbove(int $portCount): array
    {
        return $this->ports()
            ->where('number', '>', $portCount)
            ->get()
            ->filter(fn (PatchPort $port) => $port->isDocumented())
            ->pluck('number')
            ->all();
    }
}
