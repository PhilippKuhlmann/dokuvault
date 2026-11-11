<?php

namespace App\Livewire;

use App\Models\AgentInstallation;
use App\Support\AgentScript;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Admin -> Statistik -> Agenten: all installed agents of all customers -
 * how many report, which are silent, which runs fail, which still run an
 * old version (self-update stuck).
 */
class AdminAgentStats extends Component
{
    public function mount(): void
    {
        Gate::authorize('admin_stats');
    }

    public function render()
    {
        // Not only in mount(): the component keeps answering updates.
        Gate::authorize('admin_stats');

        // Current version per kind: the Windows exe, for Linux/Proxmox the
        // shared installer (see Agent\InstallationController::update).
        $aktuell = [
            'windows' => AgentScript::exeVersion(),
            'proxmox' => AgentScript::installerVersion('linux-agent.sh'),
            'linux' => AgentScript::installerVersion('linux-agent.sh'),
        ];

        $agenten = AgentInstallation::with('customer:id,name,slug')->get()
            ->map(function (AgentInstallation $a) use ($aktuell) {
                $a->veraltet = isset($aktuell[$a->kind]) && $aktuell[$a->kind] && $a->version !== $aktuell[$a->kind];
                $a->fehler = $a->failedRoles();

                return $a;
            })
            // Problems first: silent, failed, outdated - then by customer.
            ->sortBy(fn ($a) => [$a->isStale() ? 0 : ($a->fehler ? 1 : ($a->veraltet ? 2 : 3)), $a->customer?->name, $a->hostname])
            ->values();

        return view('livewire.admin-agent-stats', [
            'agenten' => $agenten,
            'summe' => [
                'gesamt' => $agenten->count(),
                'active' => $agenten->reject->isStale()->count(),
                'still' => $agenten->filter->isStale()->count(),
                'fehler' => $agenten->filter(fn ($a) => $a->fehler)->count(),
                'veraltet' => $agenten->where('veraltet', true)->count(),
            ],
            'jeArt' => $agenten->groupBy('kind')->map->count(),
            'aktuell' => $aktuell,
        ])->layout('layouts.admin.app');
    }
}
