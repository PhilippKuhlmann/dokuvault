<?php

namespace App\Livewire;

use App\Models\OperatingSystem;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Der Betriebssystem-Katalog mit Suche.
 *
 * Vorher eine statische Liste ohne Suche - bei 55 Eintraegen ueber drei
 * Seiten war ein bestimmtes System nur ueber Blaettern zu finden. Anlegen
 * und Bearbeiten bleiben eigene Seiten (OperatingSystemController): Anders
 * als beim VLAN-Modal gibt es hier keinen Grund fuer ein Livewire-Formular.
 */
class AdminOperatingSystem extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $suche = '';

    public function mount(): void
    {
        Gate::authorize('admin_catalog');
    }

    public function updatedSuche(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $abfrage = OperatingSystem::orderBy('name');

        if ($this->suche !== '') {
            $abfrage->whereEnthaelt('name', $this->suche);
        }

        return view('livewire.admin-operating-system', [
            'operatingSystems' => $abfrage->paginate(20),
            'operatingSystemsCount' => OperatingSystem::count(),
        ])->layout('layouts.admin.app');
    }
}
