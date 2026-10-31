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

    /** Support has ended - there are no security updates any more. */
    public function istEol(): bool
    {
        return $this->eol_date !== null && $this->eol_date->isPast();
    }

    /**
     * Support ends soon.
     *
     * Half a year ahead: replacing a server wants planning, budgeting and a
     * maintenance window - with 30 days' warning it is too late.
     * Einstellbar unter Einstellungen > Fristen.
     */
    public function laeuftAus(?int $tage = null): bool
    {
        $tage ??= Setting::eolDeadline();

        return $this->eol_date !== null
            && ! $this->istEol()
            && $this->eol_date->lessThanOrEqualTo(now()->addDays($tage));
    }
}
