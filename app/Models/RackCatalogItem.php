<?php

namespace App\Models;

use App\Models\Concerns\HasImage;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Passive rack element (patch panel, blanking plate, shelf ...) maintained in
 * the admin area and mountable in every rack.
 *
 * On mounting the label is copied into rack_items - an entry changed later
 * geaenderter oder geloeschter Katalogeintrag veraendert also keine
 * bestehende Rack-Dokumentation.
 */
class RackCatalogItem extends Model
{
    use HasFactory;
    use HasImage;
    use TracksChanges;

    /** Folder on the local disk and route that serves the image - see HasImage. */
    public const BILDORDNER = 'rack-catalog';

    public const BILDROUTE = 'rackcatalogitem.image';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'full_depth' => 'boolean',
    ];

    /** Order in the palette of the rack editor. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
