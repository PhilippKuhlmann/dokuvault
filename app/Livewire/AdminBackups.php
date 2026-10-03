<?php

namespace App\Livewire;

use App\Models\Backup;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Backups of all customers with their last runs, as the agents report them
 * (Veeam, Windows Server-Sicherung, Proxmox vzdump). The morning question
 * "did everything run last night?" without opening every customer.
 *
 * How many runs per backup: a setting of the installation, changed right on
 * this page - whoever looks here is the one who knows how far back to see.
 */
class AdminBackups extends Component
{
    /** Only this customer (id). */
    #[Url(except: '')]
    public string $kunde = '';

    /** Only backups whose last run failed or warned. */
    #[Url(except: false)]
    public bool $nurProbleme = false;

    public int $anzahl = 10;

    /** Runs kept per backup before the oldest are deleted. */
    public int $aufbewahrung = 100;

    public function mount(): void
    {
        Gate::authorize('admin_backup');

        $this->anzahl = Setting::backupVerlauf();
        $this->aufbewahrung = Setting::backupAufbewahrung();
    }

    public function updatedAnzahl(): void
    {
        Gate::authorize('admin_backup');

        $this->anzahl = max(5, min(50, $this->anzahl));
        Setting::setzen(Setting::BACKUP_VERLAUF, $this->anzahl);
        // Showing more than is kept would show nothing more.
        $this->aufbewahrung = Setting::backupAufbewahrung();
    }

    /**
     * Saves the retention and applies it at once: lowered from 1000 to 100,
     * the database should shrink now, not with the next report of each job.
     */
    public function updatedAufbewahrung(): void
    {
        Gate::authorize('admin_backup');

        $this->validate(['aufbewahrung' => ['required', 'integer', 'min:'.max(10, $this->anzahl), 'max:10000']], [], [
            'aufbewahrung' => __('Höchstens speichern'),
        ]);

        Setting::setzen(Setting::BACKUP_AUFBEWAHRUNG, $this->aufbewahrung);

        $geloescht = 0;
        Backup::has('runs', '>', $this->aufbewahrung)->each(function (Backup $backup) use (&$geloescht) {
            $geloescht += $backup->pruneRuns($this->aufbewahrung);
        });

        $this->dispatch('hinweis', text: $geloescht > 0
            ? __(':anzahl ältere Läufe gelöscht.', ['anzahl' => $geloescht])
            : __('Gespeichert.'));
    }

    public function render()
    {
        $backups = Backup::query()
            ->with(['customer:id,name,slug', 'recentRuns'])
            // Only what an agent reports: hand-entered backups have no runs.
            ->whereNotNull('agent_identifier')
            ->when($this->kunde !== '', fn ($q) => $q->where('customer_id', (int) $this->kunde))
            ->when($this->nurProbleme, fn ($q) => $q->whereIn('last_status', ['failed', 'warning']))
            ->get()
            // Problems first, then by customer and name.
            ->sortBy(fn ($b) => [match ($b->last_status) {
                'failed' => 0, 'warning' => 1, default => 2
            }, $b->customer?->name, $b->name])
            ->values();

        return view('livewire.admin-backups', [
            'backups' => $backups,
            'kunden' => Customer::whereIn('id', Backup::whereNotNull('agent_identifier')->select('customer_id'))
                ->orderBy('name')->pluck('name', 'id')->all(),
        ])->layout('layouts.admin.app');
    }
}
