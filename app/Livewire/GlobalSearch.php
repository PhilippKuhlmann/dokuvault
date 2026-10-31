<?php

namespace App\Livewire;

use App\Support\Search;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class GlobalSearch extends Component
{
    #[Url]
    public $search;

    /**
     * The hits, grouped by kind.
     *
     * As a computed property instead of inline in render(): that way the
     * search can be tested on its own without rendering the view, and render()
     * says in one line again what it does. The search itself sits in
     * App\Support\Search - the app asks the same question.
     */
    #[Computed]
    public function groups()
    {
        return app(Search::class)->groups(auth()->user(), (string) $this->search);
    }

    public function render()
    {
        return view('livewire.global-search', ['groups' => $this->groups])
            ->layout('layouts.empty', ['title' => 'Globale Suche']);
    }
}
