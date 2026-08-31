<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactPerson extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Ein Ansprechpartner heisst mit Vor- und Nachnamen.
     *
     * Keines der beiden Felder allein taugt: "Torben" sagt im Protokoll so
     * wenig wie "Ahlers", und die zentrale Liste in config/custom.php kann
     * nur ein Feld nehmen, nicht zwei.
     */
    public function protokollName(): ?string
    {
        return trim($this->first_name.' '.$this->last_name) ?: null;
    }
}
