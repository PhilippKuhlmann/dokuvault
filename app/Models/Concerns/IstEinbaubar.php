<?php

namespace App\Models\Concerns;

use App\Models\Rack;
use App\Models\RackItem;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Gegenstueck zu RackItem::device(): Von einem Geraet aus finden, wo es
 * eingebaut ist. Ohne das muesste jede Geraeteliste selbst in rack_items
 * suchen.
 */
trait IstEinbaubar
{
    public function rackItem(): MorphOne
    {
        return $this->morphOne(RackItem::class, 'device');
    }

    /**
     * Kurzfassung des Einbauorts fuer Listen und PDF, z. B.
     * "Rack HH-01 · HE 4–5 · Vorderseite". Null, wenn nicht eingebaut.
     */
    public function einbauort(): ?string
    {
        $einbau = $this->rackItem;

        if (! $einbau?->rack) {
            return null;
        }

        $he = $einbau->height_units > 1
            ? 'HE '.$einbau->position.'–'.$einbau->topUnit()
            : 'HE '.$einbau->position;

        return $einbau->rack->name.' · '.$he.' · '.__(Rack::SEITEN[$einbau->side] ?? 'Vorderseite');
    }
}
