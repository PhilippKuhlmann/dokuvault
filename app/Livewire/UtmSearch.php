<?php

namespace App\Livewire;

use App\Models\SecurepointUTM;
use Livewire\Component;

class UtmSearch extends Component
{
    public $search;

    protected $queryString = ['search'];

    public function render()
    {
        if ($this->search) {
            $search = $this->search;
            $utms = SecurepointUTM::where('urlExternal', '!=', ' ')
                ->with('customer')
                ->with('site')
                ->join('customers', 'securepoint_utms.customer_id', '=', 'customers.id')
                ->where('customers.name', 'like', "%$search%")
                ->orderBy('customers.name')
                ->get();
        } else {
            $utms = SecurepointUTM::where('urlExternal', '!=', ' ')
                ->with('customer')
                ->with('site')
                ->join('customers', 'securepoint_utms.customer_id', '=', 'customers.id')
                ->orderBy('customers.name')
                ->get();
        }

        return view('livewire.utm-search', [
            'utms' => $utms,
        ])
            ->layout('layouts.empty', ['title' => 'UTM']);
    }
}
