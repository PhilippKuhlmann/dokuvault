<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Network;
use App\Support\IpPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * IP plan per VLAN. The computation sits in IpPlan - the same one for the
 * iOS app; here only the site filter, the links and the page.
 */
class IpPlanController extends Controller
{
    /** Modellklasse => Listen-URL-Baustein oder null, je Anfrage einmal ermittelt. */
    protected array $listen = [];

    protected Customer $kunde;

    public function index(Customer $customer)
    {
        $this->authorize('viewAny', Network::class);
        $this->kunde = $customer;

        // Show only the VLANs of the chosen site.
        $networks = $this->getFilteredQuery(Network::class, $customer)
            ->orderBy('vlanId')
            ->get();

        $plans = (new IpPlan($this->deviceEntry(...)))->plans($customer, $networks);

        $totalUsed = $plans->sum(fn ($entry) => $entry['plan']['usedCount'] ?? 0);
        $totalAddresses = $plans->sum(fn ($entry) => $entry['plan']['total'] ?? 0);

        return view('ipplan.index', compact('customer', 'plans', 'totalUsed', 'totalAddresses'));
    }

    /**
     * A device as it stands in the plan: the name and - where the user may
     * see the list - the jump there, straight onto the device's card
     * (?highlight=, the same way as from the global search).
     *
     * @return array{name: string, url: ?string}
     */
    protected function deviceEntry(?Model $geraet, string $zusatz = ''): array
    {
        $typ = $geraet ? $this->listType($geraet::class) : null;

        return [
            'name' => IpPlan::deviceName($geraet).$zusatz,
            'url' => $typ
                ? route($typ.'.index', [$this->kunde, 'highlight' => $geraet->id])
                : null,
        ];
    }

    /**
     * The object kind from config('forms') whose list shows this model -
     * null when there is none or the user may not see it. A link to a
     * forbidden page would only be a detour to the error message.
     */
    protected function listType(string $klasse): ?string
    {
        if (! array_key_exists($klasse, $this->listen)) {
            $typ = collect(config('forms'))->search(fn ($d) => ($d['model'] ?? null) === $klasse) ?: null;

            $this->listen[$klasse] = $typ && Route::has($typ.'.index') && Gate::allows($typ.'_viewAny')
                ? $typ
                : null;
        }

        return $this->listen[$klasse];
    }
}
