<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rack extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        // Absteigend nach Position: oberstes Geraet zuerst, wie in der Frontansicht.
        return $this->hasMany(RackItem::class)->orderByDesc('position');
    }

    /** Die Seiten, die es gibt - Schluessel und Beschriftung. */
    public const SEITEN = ['front' => 'Vorderseite', 'rear' => 'Rückseite'];

    /**
     * Was auf einer Seite zu sehen ist: die dort eingebauten Geraete, und
     * zusaetzlich die von der anderen Seite, die in voller Tiefe durchgehen.
     * Letztere belegen den Platz, lassen sich hier aber nicht bearbeiten.
     */
    public function itemsFuerSeite(string $seite)
    {
        return $this->items->filter(fn (RackItem $item) => $item->belegtSeite($seite));
    }
}
