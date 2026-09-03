<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperatingSystem extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'eol_date' => 'date',
    ];

    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    public function vms()
    {
        return $this->hasMany(VM::class);
    }

    /** Der Support ist abgelaufen - es gibt keine Sicherheitsupdates mehr. */
    public function istEol(): bool
    {
        return $this->eol_date !== null && $this->eol_date->isPast();
    }

    /**
     * Der Support endet bald.
     *
     * Ein halbes Jahr Vorlauf: Ein Serverwechsel will geplant, budgetiert und
     * in ein Wartungsfenster gelegt werden - mit 30 Tagen Warnung ist es zu spät.
     * Einstellbar unter Einstellungen > Fristen.
     */
    public function laeuftAus(?int $tage = null): bool
    {
        $tage ??= Setting::fristEol();

        return $this->eol_date !== null
            && ! $this->istEol()
            && $this->eol_date->lessThanOrEqualTo(now()->addDays($tage));
    }
}
