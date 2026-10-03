<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

class Backup extends Model
{
    use HasFactory, SoftDeletes;
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
     * The runs that are shown (Setting::backupVerlauf) - eager loadable with
     * a limit per backup, so a list does not load every run of every job.
     */
    public function recentRuns()
    {
        return $this->runs()->limit(Setting::backupVerlauf());
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
