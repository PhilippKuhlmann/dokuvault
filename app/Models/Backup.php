<?php

namespace App\Models;

use App\Models\Concerns\OwnedByCustomer;
use App\Models\Concerns\TracksChanges;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class Backup extends Model
{
    use HasFactory, SoftDeletes;
    use OwnedByCustomer;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'last_run_at' => 'datetime',
    ];

    /** Outcome of the last run as reported by an agent: label per status. */
    public const STATUS = [
        'ok' => 'Erfolgreich',
        'warning' => 'Mit Warnungen',
        'failed' => 'Fehlgeschlagen',
    ];

    protected function password(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => ! empty($value) ? Crypt::encryptString($value) : null,
        );
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** Reported runs, newest first. */
    public function runs()
    {
        return $this->hasMany(BackupRun::class)->latest('finished_at');
    }

    /**
     * The runs of the period the grid shows (Setting::backupDays), counted
     * in whole local days - today included. Eager loadable, so a list does
     * not load every run of every job.
     */
    public function recentRuns()
    {
        $since = Carbon::now(LocalTime::zone())->subDays(Setting::backupDays() - 1)->startOfDay()->utc();

        return $this->runs()->where('finished_at', '>=', $since);
    }

    /**
     * The oldest run kept: days before it stand empty in the grid, not grey
     * - nothing is known about them (job newer, or the runs pruned).
     */
    public function firstRun()
    {
        return $this->hasOne(BackupRun::class)->oldestOfMany('finished_at');
    }

    /** Deletes all but the newest $behalten runs of this backup. */
    public function pruneRuns(int $behalten): int
    {
        $ids = $this->runs()->limit($behalten)->pluck('id');

        return $this->runs()->whereNotIn('id', $ids)->delete();
    }

    /**
     * Records reported runs (status + end time); one already known is
     * updated, not doubled. Beyond Setting::backupAufbewahrung the oldest
     * are dropped - the table must not grow forever.
     *
     * @param  array<int, array{status: string, finished_at: string}>  $runs
     */
    public function recordRuns(array $runs): void
    {
        foreach ($runs as $run) {
            $this->runs()->updateOrCreate(
                ['finished_at' => Carbon::parse($run['finished_at'])],
                ['status' => $run['status']]
            );
        }

        $this->pruneRuns(Setting::backupAufbewahrung());
    }
}
