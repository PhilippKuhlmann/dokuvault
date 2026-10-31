<?php

namespace App\Http\Controllers;

use App\Models\Cluster;
use App\Models\Customer;

class ClusterController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Cluster::class);

        // List and form are Livewire (see config/forms.php):
        // the view therefore needs the customer only.
        return view('cluster.index', compact('customer'));
    }
}
