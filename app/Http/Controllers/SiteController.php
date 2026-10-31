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

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('site.index', compact('customer'));
    }
}
