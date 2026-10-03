<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One run of a backup job, as reported by an agent (see the migration).
 * Not tracked in the activity log: a run is a measurement, not a change
 * someone made.
 */
class BackupRun extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'finished_at' => 'datetime',
    ];

    public function backup()
    {
        return $this->belongsTo(Backup::class);
    }
}
