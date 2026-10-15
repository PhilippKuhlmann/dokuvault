<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Network;
use App\Support\NetworkMap;

class NetworkMapController extends Controller
{
    public function index(Customer $customer, NetworkMap $map)
    {
        $this->authorize('viewAny', Network::class);

        // The site filter of the sidebar, as in every other list. A site of
        // another customer left in the session counts as no filter.
        $site = session('site');
        $siteId = $site && $site !== 'all' && $customer->sites()->whereKey($site)->exists() ? (int) $site : null;

        $sites = $map->build($customer, $siteId);

        return view('networkmap.index', compact('customer', 'sites'));
    }
}
