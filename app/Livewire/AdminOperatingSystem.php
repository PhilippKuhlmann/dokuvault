<?php

namespace App\Livewire;

use App\Models\OperatingSystem;
use App\Models\Setting;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The operating system catalog with search.
 *
 * It used to be a static list without search - at 55 entries across three
 * pages a particular system could only be found by paging. Creating and
 * editing stay pages of their own (OperatingSystemController): unlike with
 * the VLAN modal there is no reason for a Livewire form here.
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
            'operatingSystems' => $abfrage->paginate(Setting::seiteAdmin()),
            'operatingSystemsCount' => OperatingSystem::count(),
        ])->layout('layouts.admin.app');
    }
}
