<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single port of a patch panel: which network outlet it serves and which
 * switch port it is patched to.
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
     * Display name for the global search - a port has no name column, but the
     * hit list reaches for it.
     */
    public function getNameAttribute(): string
    {
        $dose = trim($this->outlet.' '.$this->label);

        return $dose !== ''
            ? $dose.' (Port '.$this->number.')'
            : 'Port '.$this->number;
    }

    /**
     * An outlet is named after its label - the same answer as name.
     *
     * The method otherwise belongs to TracksChanges; an outlet is not logged,
     * but the global search asks every hit for it.
     */
    public function activityName(): ?string
    {
        return $this->name ?: null;
    }

    public function networkSwitch()
    {
        return $this->belongsTo(NetworkSwitch::class, 'network_switch_id');
    }

    /** Is there anything on this port that would be lost on deletion? */
    public function isDocumented(): bool
    {
        return filled($this->outlet) || filled($this->label) || filled($this->network_switch_id)
            || filled($this->switch_port) || filled($this->note);
    }
}
