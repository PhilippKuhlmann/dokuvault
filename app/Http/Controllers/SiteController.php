<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Site;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function filter(Customer $customer, Request $request)
    {

        session()->put('site', $request->site);

        return back();
    }

    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Site::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('site.index', compact('customer'));
    }
}
