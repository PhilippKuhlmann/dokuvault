<?php

namespace App\Http\Controllers\API\Agent\Concerns;

use App\Models\Backup;
use Illuminate\Support\Carbon;

/**
 * Backup jobs and their last runs - reported by the backup agents and, for
 * its vzdump jobs, by a Proxmox host.
 */
trait ReportsBackups
{
    /** Validation for a list of reported backup jobs under $feld. */
    private function backupRegeln(string $feld): array
    {
        return [
            $feld => ['nullable', 'array', 'max:500'],
            $feld.'.*.identifier' => ['required', 'string', 'max:255'],
            $feld.'.*.name' => ['nullable', 'string', 'max:255'],
            $feld.'.*.software' => ['nullable', 'string', 'max:255'],
            $feld.'.*.source' => ['nullable', 'string', 'max:255'],
            $feld.'.*.destination' => ['nullable', 'string', 'max:255'],
            $feld.'.*.schedule' => ['nullable', 'string', 'max:255'],
            $feld.'.*.retention' => ['nullable', 'string', 'max:255'],
            $feld.'.*.last_status' => ['nullable', 'in:'.implode(',', array_keys(Backup::STATUS))],
            $feld.'.*.last_run_at' => ['nullable', 'date'],
            $feld.'.*.last_success' => ['nullable', 'date'],
            // Recent runs (since 26.10.03) - the history on the admin overview.
            $feld.'.*.runs' => ['nullable', 'array', 'max:100'],
            $feld.'.*.runs.*.status' => ['required', 'in:'.implode(',', array_keys(Backup::STATUS))],
            $feld.'.*.runs.*.finished_at' => ['required', 'date'],
        ];
    }

    /**
     * Create or update reported backup jobs. Found by agent_identifier, else
     * a job documented by hand with the same name is adopted (as with AD
     * users). Only what was reported is written - notes and password stay.
     * Nothing is deleted: a job removed in Veeam stays until someone
     * removes it here.
     */
    private function backupsDokumentieren(int $customerId, array $jobs): int
    {
        foreach ($jobs as $job) {
            $backup = Backup::where('customer_id', $customerId)->where('agent_identifier', $job['identifier'])->first();

            if (! $backup && filled($job['name'] ?? null)) {
                $backup = Backup::where('customer_id', $customerId)
                    ->whereNull('agent_identifier')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($job['name'])])
                    ->orderBy('id')
                    ->first();
            }

            $backup ??= new Backup(['customer_id' => $customerId]);

            $backup->fill(array_filter([
                'agent_identifier' => $job['identifier'],
                'name' => $job['name'] ?? null,
                'software' => $job['software'] ?? null,
                'source' => $job['source'] ?? null,
                'destination' => $job['destination'] ?? null,
                'schedule' => $job['schedule'] ?? null,
                'retention' => $job['retention'] ?? null,
                'last_status' => $job['last_status'] ?? null,
                'last_run_at' => filled($job['last_run_at'] ?? null) ? Carbon::parse($job['last_run_at']) : null,
                'last_success' => filled($job['last_success'] ?? null) ? Carbon::parse($job['last_success'])->toDateString() : null,
            ], fn ($wert) => filled($wert)));

            // A name is needed for the list; a job without one is named by
            // its software.
            $backup->name ??= ($job['software'] ?? 'Backup');
            $backup->save();

            // The history: the runs sent along, and the last run itself - a
            // script without "runs" still builds up a history over time.
            $runs = $job['runs'] ?? [];
            if (filled($job['last_run_at'] ?? null) && filled($job['last_status'] ?? null)) {
                $runs[] = ['status' => $job['last_status'], 'finished_at' => $job['last_run_at']];
            }
            $backup->recordRuns($runs);
        }

        return count($jobs);
    }
}
