<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein Einbau im Rack: entweder ein verknuepftes dokumentiertes Geraet
 * (device_type/device_id) oder ein passives Katalogelement (nur name).
 */
class RackItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }

    public function device()
    {
        return $this->morphTo();
    }

    /** Anzeigename: Geraetename aus der Doku bzw. Katalogbezeichnung. */
    public function label(): string
    {
        return $this->device?->name ?? $this->name ?? '—';
    }

    /** Oberste belegte Hoeheneinheit. */
    public function topUnit(): int
    {
        return $this->position + $this->height_units - 1;
    }

    /**
     * Darstellung in der gezeichneten Frontansicht. Bei dokumentierten Geraeten
     * ergibt sie sich aus dem Geraetetyp, bei Katalogelementen steht sie in der
     * beim Einbau kopierten Spalte.
     */
    public function faceAppearance(): string
    {
        if ($this->device_type) {
            foreach (config('custom.rack_device_types') as [$class, $label, $appearance]) {
                if ($class === $this->device_type) {
                    return $appearance;
                }
            }

            return 'server';
        }

        return $this->appearance ?: 'blank';
    }
}
