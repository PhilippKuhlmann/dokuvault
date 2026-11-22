<?php

namespace App\Livewire;

use App\Models\Backup;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Backups of all customers with their run grid, as the agents report them
 * (Veeam, Windows Server-Sicherung, Proxmox vzdump). The morning question
 * "did everything run last night?" without opening every customer.
 *
 * One square per day (x-backup-verlauf): a job that stopped running shows
 * as a grey gap. How many days: a setting of the installation, changed right
 * on this page - whoever looks here is the one who knows how far back to see.
 */
class AdminBackups extends Component
{
    /** Only this customer (id). */
    #[Url(except: '')]
    public string $kunde = '';

    /** Only backups whose last run failed or warned. */
    #[Url(except: false)]
    public bool $nurProbleme = false;

    /** Days in the grid (Setting::backupDays). */
    public int $tage = 14;

    /** Runs kept per backup before the oldest are deleted. */
    public int $aufbewahrung = 100;

    public function mount(): void
    {
        Gate::authorize('admin_backup');

        $this->tage = Setting::backupDays();
        $this->aufbewahrung = Setting::backupAufbewahrung();
    }

    public function updatedTage(): void
    {
        Gate::authorize('admin_backup');

        $this->tage = in_array($this->tage, Setting::BACKUP_DAY_OPTIONS, true) ? $this->tage : 14;
        Setting::setzen(Setting::BACKUP_VERLAUF, $this->tage);
    }

    /**
     * Saves the retention and applies it at once: lowered from 1000 to 100,
     * the database should shrink now, not with the next report of each job.
     */
    public function updatedAufbewahrung(): void
    {
        Gate::authorize('admin_backup');

        $this->validate(['aufbewahrung' => ['required', 'integer', 'min:10', 'max:10000']], [], [
            'aufbewahrung' => __('Höchstens speichern'),
        ]);

        Setting::setzen(Setting::BACKUP_AUFBEWAHRUNG, $this->aufbewahrung);

        $geloescht = 0;
        Backup::has('runs', '>', $this->aufbewahrung)->each(function (Backup $backup) use (&$geloescht) {
            $geloescht += $backup->pruneRuns($this->aufbewahrung);
        });

        $this->dispatch('notice', text: $geloescht > 0
            ? __(':anzahl ältere Läufe gelöscht.', ['anzahl' => $geloescht])
            : __('Gespeichert.'));
    }

    public function render()
    {
        // Only what an agent reports: hand-entered backups have no runs.
        $reported = Backup::query()
            ->whereNotNull('agent_identifier')
            ->when($this->kunde !== '', fn ($q) => $q->where('customer_id', (int) $this->kunde));

        // The counts on top: by the last run, before "only problems" filters.
        $counts = (clone $reported)
            ->selectRaw('last_status, count(*) as anzahl')
            ->groupBy('last_status')
            ->pluck('anzahl', 'last_status');

        $backups = $reported
            ->with(['customer:id,name,slug', 'recentRuns', 'firstRun'])
            ->when($this->nurProbleme, fn ($q) => $q->whereIn('last_status', ['failed', 'warning']))
            ->get()
            // Problems first, then by customer and name.
            ->sortBy(fn ($b) => [match ($b->last_status) {
                'failed' => 0, 'warning' => 1, default => 2
            }, $b->customer?->name, $b->name])
            ->values();

        return view('livewire.admin-backups', [
            'backups' => $backups,
            'counts' => [
                'failed' => (int) ($counts['failed'] ?? 0),
                'warning' => (int) ($counts['warning'] ?? 0),
                'ok' => (int) ($counts['ok'] ?? 0),
            ],
            'kunden' => Customer::whereIn('id', Backup::whereNotNull('agent_identifier')->select('customer_id'))
                ->orderBy('name')->pluck('name', 'id')->all(),
        ])->layout('layouts.admin.app');
    }
}
