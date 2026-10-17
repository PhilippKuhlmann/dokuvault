<?php

namespace App\Livewire;

use App\Support\GlobaleSuche;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class GlobalSearch extends Component
{
    #[Url]
    public $search;

    /**
     * Die Treffer, nach Typ gruppiert.
     *
     * Als computed property statt inline in render(): So laesst sich die Suche
     * einzeln pruefen, ohne die View mitzurendern, und render() sagt wieder in
     * einer Zeile, was es tut. Die Suche selbst steht in App\Support\GlobaleSuche -
     * die App stellt dieselbe Frage.
     */
    #[Computed]
    public function groups()
    {
        return app(GlobaleSuche::class)->gruppen(auth()->user(), (string) $this->search);
    }

    public function render()
    {
        return view('livewire.global-search', ['groups' => $this->groups])
            ->layout('layouts.empty', ['title' => 'Globale Suche']);
    }
}
