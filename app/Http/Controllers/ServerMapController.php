<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Server;
use App\Support\ServerMap;

class ServerMapController extends Controller
{
    public function index(Customer $customer, ServerMap $map)
    {
        $this->authorize('viewAny', Server::class);

        // The site filter of the sidebar, as in the network map.
        $site = session('site');
        $siteId = $site && $site !== 'all' && $customer->sites()->whereKey($site)->exists() ? (int) $site : null;

        $sites = $map->build($customer, $siteId);

        return view('servermap.index', compact('customer', 'sites'));
    }
}
