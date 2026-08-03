<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Component;

class SearchCustomer extends Component
{
    public $search;

    protected $queryString = ['search'];

    public function render()
    {

        $customers = null;

        if ($this->search) {
            $customers = Customer::where('name', 'like', '%'.$this->search.'%')->get();
            if ($customers->isempty()) {
                $customers = null;
            }
        }

        return view('livewire.search-customer', [
            'customers' => $customers,
        ]);
    }
}
