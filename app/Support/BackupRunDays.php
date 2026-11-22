<?php

namespace App\Support;

use App\Models\Backup;
use App\Models\BackupRun;
use Illuminate\Support\Carbon;

/**
 * The run grid of a backup: one cell per local day, oldest left, today
 * right. A job that silently stopped running stayed green while the grid
 * counted runs - counted in days, the gap shows.
 *
 * Per day the worst run counts (failed over warning over ok). A day without
 * a run is "none"; a day before the oldest run kept is "unknown" - the job
 * may be newer, or its runs were pruned (Setting::backupAufbewahrung).
 */
class BackupRunDays
{
    /** Worst first. */
    protected const RANK = ['failed' => 0, 'warning' => 1, 'ok' => 2];

    /**
     * @return list<array{date: string, state: string, runs: int, label: string}>
     */
    public static function for(Backup $backup, int $days): array
    {
        $zone = LocalTime::zone();
        $today = Carbon::now($zone)->startOfDay();
        $first = $backup->firstRun?->finished_at?->copy()->setTimezone($zone)->startOfDay();

        $byDay = $backup->recentRuns
            ->groupBy(fn (BackupRun $run) => $run->finished_at->copy()->setTimezone($zone)->toDateString());

        $cells = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $runs = $byDay->get($day->toDateString(), collect());

            $state = match (true) {
                $runs->isNotEmpty() => $runs->sortBy(fn (BackupRun $run) => self::RANK[$run->status] ?? 3)->first()->status,
                $first === null || $day->lt($first) => 'unknown',
                default => 'none',
            };

            $cells[] = [
                'date' => $day->toDateString(),
                'state' => $state,
                'runs' => $runs->count(),
                'label' => self::label($day, $state, $runs->count()),
            ];
        }

        return $cells;
    }

    /** For the tooltip and screen readers: "21.11.2026 · Fehlgeschlagen (3 Läufe)". */
    protected static function label(Carbon $day, string $state, int $runs): string
    {
        $text = match ($state) {
            'none' => __('kein Lauf'),
            'unknown' => __('noch nicht erfasst'),
            default => __(Backup::STATUS[$state] ?? $state),
        };

        if ($runs > 1) {
            $text .= ' ('.trans_choice(':count Lauf|:count Läufe', $runs).')';
        }

        return $day->format('d.m.Y').' · '.$text;
    }
}
