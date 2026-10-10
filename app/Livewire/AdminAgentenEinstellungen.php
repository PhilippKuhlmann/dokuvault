<?php

namespace App\Livewire;

use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Admin -> Einstellungen -> Agenten: defaults for all customers - interval
 * of new agents, when an agent counts as silent, validity of agent tokens.
 * Formerly fixed in config/custom.php and in the code (3 hours, 365 days).
 *
 * Saves while typing, like Fristen and Sicherheit.
 */
class AdminAgentenEinstellungen extends Component
{
    public int $intervall = 0;

    public int $stillStunden = 0;

    public int $tokenTage = 0;

    public int $tokenMaxTage = 0;

    public bool $autoCheck = true;

    public function mount(): void
    {
        Gate::authorize('admin_setting');

        $this->intervall = Setting::agentIntervall();
        $this->stillStunden = Setting::agentStillStunden();
        $this->tokenTage = Setting::agentTokenTage();
        $this->tokenMaxTage = Setting::agentTokenMaxTage();
        $this->autoCheck = Setting::autoCheck();
    }

    public function updatedAutoCheck(): void
    {
        Gate::authorize('admin_setting');

        Setting::setzen(Setting::AUTO_CHECK, $this->autoCheck ? '1' : '0');
        $this->dispatch('hinweis', text: __('Einstellung gespeichert.'));
    }

    public function updatedIntervall(): void
    {
        Gate::authorize('admin_setting');
        $this->validate(
            ['intervall' => ['required', 'integer', Rule::in(array_keys(config('custom.agent_intervalle')))]],
            [],
            ['intervall' => __('Intervall')]
        );

        Setting::setzen(Setting::AGENT_INTERVALL, $this->intervall);
        $this->dispatch('hinweis', text: __('Einstellung gespeichert.'));
    }

    public function updatedStillStunden(): void
    {
        $this->zahl('stillStunden', Setting::AGENT_STILL_STUNDEN, __('Meldet nicht ab'));
    }

    public function updatedTokenTage(): void
    {
        // The default can't be above the maximum - the form would offer a
        // date the check then refuses.
        $this->zahl('tokenTage', Setting::AGENT_TOKEN_TAGE, __('Gültigkeit neuer Tokens'), $this->tokenMaxTage);
    }

    public function updatedTokenMaxTage(): void
    {
        $this->zahl('tokenMaxTage', Setting::AGENT_TOKEN_MAX_TAGE, __('Höchstens'));

        if ($this->tokenTage > $this->tokenMaxTage) {
            $this->tokenTage = $this->tokenMaxTage;
            Setting::setzen(Setting::AGENT_TOKEN_TAGE, $this->tokenTage);
        }
    }

    private function zahl(string $feld, string $schluessel, string $bezeichnung, ?int $hoechstens = null): void
    {
        Gate::authorize('admin_setting');
        [, $min, $max] = Setting::ZAHLEN[$schluessel];

        $this->validate(
            [$feld => ['required', 'integer', 'min:'.$min, 'max:'.min($max, $hoechstens ?? $max)]],
            [],
            [$feld => $bezeichnung]
        );

        Setting::setzen($schluessel, $this->$feld);
        $this->dispatch('hinweis', text: __('Einstellung gespeichert.'));
    }

    public function render()
    {
        return view('livewire.admin-agenten-einstellungen', [
            'intervalle' => config('custom.agent_intervalle'),
        ])->layout('layouts.admin.app');
    }
}
