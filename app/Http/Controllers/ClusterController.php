<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClusterRequest;
use App\Models\Cluster;
use App\Models\Customer;

class ClusterController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Cluster::class);

        // Liste und Formular sind Livewire (objekt-liste, ueber config/forms.php) -
        // wie bei den Servern. Die Ansicht braucht deshalb nur den Kunden.
        return view('cluster.index', compact('customer'));
    }

    public function create(Customer $customer)
    {
        $this->authorize('create', Cluster::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('cluster.create', compact('customer', 'sites'));
    }

    public function store(Customer $customer, ClusterRequest $request)
    {
        $this->authorize('create', Cluster::class);

        $cluster = $customer->clusters()->create($request->validated());

        // Direkt ins Bearbeiten: Ein frischer Cluster hat noch keine Knoten,
        // und die weist man dort zu.
        return redirect(route('cluster.edit', [$customer, $cluster]));
    }

    public function edit(Customer $customer, Cluster $cluster)
    {
        $this->authorize('update', Cluster::class);

        $sites = $this->getSitesForCustomer($customer);

        return view('cluster.edit', compact('customer', 'cluster', 'sites'));
    }

    public function update(Customer $customer, Cluster $cluster, ClusterRequest $request)
    {
        $this->authorize('update', Cluster::class);

        $cluster->update($request->validated());

        return redirect(route('cluster.index', $customer));
    }

    public function destroy(Customer $customer, Cluster $cluster)
    {
        $this->authorize('delete', Cluster::class);

        // Die Server bleiben stehen und verlieren nur ihre Zugehoerigkeit: Der
        // Cluster wandert in den Papierkorb, die Fremdschluessel-Regel
        // (nullOnDelete) greift aber erst beim endgueltigen Loeschen.
        $cluster->servers()->update(['cluster_id' => null]);

        $cluster->delete();

        return redirect(route('cluster.index', $customer));
    }
}
