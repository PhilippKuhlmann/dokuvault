<?php

namespace App\Livewire;

use App\Models\AgentInstallation;
use App\Support\AgentSkript;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Admin -> Statistik -> Agenten: all installed agents of all customers -
 * how many report, which are silent, which runs fail, which still run an
 * old version (self-update stuck).
 */
class AdminAgentenStatistik extends Component
{
    public function mount(): void
    {
        Gate::authorize('admin_statistik');
    }

    public function render()
    {
        // Current version per kind: the Windows exe, for Linux/Proxmox the
        // shared installer (see AgentController::update).
        $aktuell = [
            'windows' => AgentSkript::exeVersion(),
            'proxmox' => AgentSkript::installerVersion('linux-agent.sh'),
            'linux' => AgentSkript::installerVersion('linux-agent.sh'),
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

        return view('livewire.admin-agenten-statistik', [
            'agenten' => $agenten,
            'summe' => [
                'gesamt' => $agenten->count(),
                'aktiv' => $agenten->reject->isStale()->count(),
                'still' => $agenten->filter->isStale()->count(),
                'fehler' => $agenten->filter(fn ($a) => $a->fehler)->count(),
                'veraltet' => $agenten->where('veraltet', true)->count(),
            ],
            'jeArt' => $agenten->groupBy('kind')->map->count(),
            'aktuell' => $aktuell,
        ])->layout('layouts.admin.app');
    }
}
