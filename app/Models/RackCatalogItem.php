<?php

namespace App\Models;

use App\Models\Concerns\HatBild;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Passives Rack-Element (Patchfeld, Blindplatte, Fachboden ...), das im
 * Adminbereich gepflegt wird und in jedem Rack einbaubar ist.
 *
 * Beim Einbau wird die Bezeichnung nach rack_items kopiert - ein spaeter
 * geaenderter oder geloeschter Katalogeintrag veraendert also keine
 * bestehende Rack-Dokumentation.
 */
class RackCatalogItem extends Model
{
    use HasFactory;
    use HatBild;
    use TracksChanges;

    /** Ordner auf der local-Disk und Route, die das Bild ausliefert - siehe HatBild. */
    public const BILDORDNER = 'rack-catalog';

    public const BILDROUTE = 'rackcatalogitem.image';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'full_depth' => 'boolean',
    ];

    /** Reihenfolge in der Palette des Rack-Editors. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
