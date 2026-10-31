<?php

namespace App\Livewire;

use App\Models\PasswordHistory;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * How long the activity log is kept.
 *
 * One number, not a list: what the log holds is seen in the log. Here it is
 * only about how long it stays there - and thereby how long the previous
 * passwords stay available, because they hang on it.
 */
class AdminLogRetention extends Component
{
    /** Aufbewahrungsfrist in Tagen, 0 heisst unbegrenzt. */
    public int $tage = 0;

    public function mount(): void
    {
        Gate::authorize('admin_activity');

        $this->tage = Setting::activityDays();
    }

    public function save(): void
    {
        Gate::authorize('admin_activity');

        $this->validate(
            ['tage' => ['required', 'integer', 'min:0', 'max:3650']],
            [],
            ['tage' => __('Aufbewahrung')]
        );

        Setting::setzen(Setting::PROTOKOLL_TAGE, $this->tage);

        $this->dispatch('notice', text: $this->tage === 0
            ? __('Das Protokoll wird unbegrenzt aufbewahrt.')
            : __('Das Protokoll wird :tage Tage aufbewahrt.', ['tage' => $this->tage]));
    }

    /**
     * How much the deadline would hit today.
     *
     * A number without an idea of its consequences is an imposition: "365"
     * does not tell anyone whether three entries disappear or three thousand.
     */
    public function render()
    {
        $grenze = $this->tage > 0 ? now()->subDays($this->tage) : null;

        return view('livewire.admin-log-retention', [
            'gesamt' => Activity::count(),
            'aeltester' => Activity::min('created_at'),
            'kennwoerter' => PasswordHistory::count(),
            'betroffen' => $grenze
                ? Activity::where('created_at', '<', $grenze)->count()
                : 0,
            'betroffeneKennwoerter' => $grenze
                ? PasswordHistory::where('created_at', '<', $grenze)->count()
                : 0,
        ])->layout('layouts.admin.app');
    }
}
