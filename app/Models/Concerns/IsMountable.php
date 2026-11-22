<?php

namespace App\Models\Concerns;

use App\Models\Rack;
use App\Models\RackItem;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * The counterpart to RackItem::device(): find from a device where it is
 * mounted. Without it every device list would have to look into rack_items
 * suchen.
 */
trait IsMountable
{
    public function rackItem(): MorphOne
    {
        return $this->morphOne(RackItem::class, 'device');
    }

    /**
     * Short form of the mounting place for lists and PDF, e.g.
     * "Rack HH-01 · HE 4-5 · front". Null when not mounted.
     */
    public function mountLocation(): ?string
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

    /**
     * The mounting place for the head line of a folded card, where a column
     * is 11rem wide: "Rack HH-01 · HE 22". The side only when it is the rear
     * - the front is what one expects. Null when not mounted.
     */
    public function mountLocationShort(): ?string
    {
        $einbau = $this->rackItem;

        if (! $einbau?->rack) {
            return null;
        }

        $he = $einbau->height_units > 1
            ? 'HE '.$einbau->position.'–'.$einbau->topUnit()
            : 'HE '.$einbau->position;

        return $einbau->rack->name.' · '.$he.($einbau->side === 'rear' ? ' · '.__('hinten') : '');
    }
}
