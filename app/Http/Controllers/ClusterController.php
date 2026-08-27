<?php

namespace App\Http\Controllers;

use App\Models\Cluster;
use App\Models\Customer;

class ClusterController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Cluster::class);

        // Liste und Formular sind Livewire (siehe config/forms.php):
        // Die Ansicht braucht deshalb nur den Kunden.
        return view('cluster.index', compact('customer'));
    }
}
