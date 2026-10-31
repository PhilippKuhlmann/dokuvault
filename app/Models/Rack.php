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

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function items()
    {
        // Descending by position: topmost device first, as in the front view.
        return $this->hasMany(RackItem::class)->orderByDesc('position');
    }

    /** The sides there are - key and label. */
    public const SEITEN = ['front' => 'Vorderseite', 'rear' => 'Rückseite'];

    /**
     * What can be seen on one side: the devices mounted there, plus the ones
     * from the other side that run through at full depth. The latter occupy
     * the space but cannot be edited here.
     */
    public function itemsFuerSeite(string $seite)
    {
        return $this->items->filter(fn (RackItem $item) => $item->belegtSeite($seite));
    }
}
