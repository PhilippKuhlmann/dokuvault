<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein einzelner Port eines Patchfelds: welche Netzwerkdose er bedient und auf
 * welchen Switch-Port er gepatcht ist.
 */
class PatchPort extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function panel()
    {
        return $this->belongsTo(PatchPanel::class, 'patch_panel_id');
    }

    /**
     * Anzeigename fuer die globale Suche - ein Port hat keine name-Spalte,
     * die Trefferliste greift aber darauf zu.
     */
    public function getNameAttribute(): string
    {
        $dose = trim($this->outlet.' '.$this->label);

        return $dose !== ''
            ? $dose.' (Port '.$this->number.')'
            : 'Port '.$this->number;
    }

    /**
     * Eine Dose heisst wie ihr Aufdruck - dieselbe Auskunft wie name.
     *
     * Die Methode gehoert sonst zu TracksChanges; eine Dose wird nicht
     * protokolliert, aber die globale Suche fragt jeden Treffer danach.
     */
    public function protokollName(): ?string
    {
        return $this->name ?: null;
    }

    public function networkSwitch()
    {
        return $this->belongsTo(NetworkSwitch::class, 'network_switch_id');
    }

    /** Steht an diesem Port etwas, das beim Loeschen verloren ginge? */
    public function isDocumented(): bool
    {
        return filled($this->outlet) || filled($this->label) || filled($this->network_switch_id)
            || filled($this->switch_port) || filled($this->note);
    }
}
