<?php

namespace App\Livewire;

use App\Models\PasswordHistory;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Wie lange das Protokoll aufbewahrt wird.
 *
 * Eine Zahl, keine Liste: Was im Protokoll steht, sieht man im Protokoll. Hier
 * geht es nur darum, wie lange es dort steht - und damit auch, wie lange die
 * bisherigen Kennwoerter nachschlagbar bleiben, denn die haengen daran.
 */
class AdminProtokollHistorie extends Component
{
    /** Aufbewahrungsfrist in Tagen, 0 heisst unbegrenzt. */
    public int $tage = 0;

    public function mount(): void
    {
        Gate::authorize('see_hidden');

        $this->tage = Setting::protokollTage();
    }

    public function speichern(): void
    {
        Gate::authorize('see_hidden');

        $this->validate(
            ['tage' => ['required', 'integer', 'min:0', 'max:3650']],
            [],
            ['tage' => __('Aufbewahrung')]
        );

        Setting::setzen(Setting::PROTOKOLL_TAGE, $this->tage);

        $this->dispatch('hinweis', text: $this->tage === 0
            ? __('Das Protokoll wird unbegrenzt aufbewahrt.')
            : __('Das Protokoll wird :tage Tage aufbewahrt.', ['tage' => $this->tage]));
    }

    /**
     * Wie viel die Frist heute treffen wuerde.
     *
     * Eine Zahl ohne Folgenabschaetzung ist eine Zumutung: "365" sagt einem
     * nicht, ob damit drei Eintraege verschwinden oder dreitausend.
     */
    public function render()
    {
        $grenze = $this->tage > 0 ? now()->subDays($this->tage) : null;

        return view('livewire.admin-protokoll-historie', [
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
