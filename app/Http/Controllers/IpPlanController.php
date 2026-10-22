<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Network;
use App\Support\IpPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * IP-Plan je VLAN. Die Rechnung steht in IpPlan - dieselbe fuer die
 * iOS-App; hier nur Standortfilter, Links und die Seite.
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

        // Nur die VLANs des gewählten Standorts anzeigen.
        $networks = $this->getFilteredQuery(Network::class, $customer)
            ->orderBy('vlanId')
            ->get();

        $plans = (new IpPlan($this->geraetEintrag(...)))->plans($customer, $networks);

        $totalUsed = $plans->sum(fn ($entry) => $entry['plan']['usedCount'] ?? 0);
        $totalAddresses = $plans->sum(fn ($entry) => $entry['plan']['total'] ?? 0);

        return view('ipplan.index', compact('customer', 'plans', 'totalUsed', 'totalAddresses'));
    }

    /**
     * Ein Gerät, wie es im Plan steht: Name und - wo der Nutzer die Liste
     * sehen darf - der Sprung dorthin, direkt auf die Karte des Geräts
     * (?highlight=, derselbe Weg wie aus der globalen Suche).
     *
     * @return array{name: string, url: ?string}
     */
    protected function geraetEintrag(?Model $geraet, string $zusatz = ''): array
    {
        $typ = $geraet ? $this->listenTyp($geraet::class) : null;

        return [
            'name' => IpPlan::geraetName($geraet).$zusatz,
            'url' => $typ
                ? route($typ.'.index', [$this->kunde, 'highlight' => $geraet->id])
                : null,
        ];
    }

    /**
     * Der Objekttyp aus config('forms'), dessen Liste dieses Modell zeigt -
     * null, wenn es keine gibt oder der Nutzer sie nicht sehen darf. Ein
     * Link auf eine verbotene Seite wäre nur ein Umweg zur Fehlermeldung.
     */
    protected function listenTyp(string $klasse): ?string
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
