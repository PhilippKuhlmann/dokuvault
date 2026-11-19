<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Server;
use App\Support\ServerMap;
use Illuminate\Http\Request;

class ServerMapController extends Controller
{
    public function index(Request $request, Customer $customer, ServerMap $map)
    {
        $this->authorize('viewAny', Server::class);

        // The site filter of the sidebar, as in the network map.
        $site = session('site');
        $siteId = $site && $site !== 'all' && $customer->sites()->whereKey($site)->exists() ? (int) $site : null;

        // Only running guests: a host with 27 switched-off templates and
        // spares buried the few that matter.
        $runningOnly = $request->boolean('running');

        $sites = $map->build($customer, $siteId, $request->user(), $runningOnly);

        return view('servermap.index', compact('customer', 'sites', 'runningOnly'));
    }
}
