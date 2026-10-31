<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Network;
use App\Support\NetworkMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Der Netzplan in der App: Internet -> Gateway -> VLAN -> Geraete, je
 * Standort.
 *
 * Gebaut wird er in App\Support\NetworkMap, derselben Klasse, die die Seite
 * im Browser zeichnet. Der Browser stellt die Spalten nebeneinander; auf dem
 * Telefon ist dasselbe ein aufklappbarer Baum - die Daten sind dieselben.
 *
 * Statt der ?highlight=-Verweise steht an jedem Knoten "typ" und "id".
 * Beides ist null beziehungsweise ohne Weg, wo der Nutzer die Liste nicht
 * sehen darf - genau dort laesst auch der Browser den Verweis weg.
 */
class NetworkMapController extends Controller
{
    public function zeigen(Request $request, Customer $customer, NetworkMap $plan): JsonResponse
    {
        $this->authorize('viewAny', Network::class);

        // Der Standortfilter der Seitenleiste, hier als Abfragewert. Ein
        // Standort eines anderen Kunden zaehlt als kein Filter.
        $standort = (int) $request->query('site');
        $standort = $standort && $customer->sites()->whereKey($standort)->exists() ? $standort : null;

        return response()->json([
            'standorte' => collect($plan->build($customer, $standort))
                ->map(fn ($eintrag) => [
                    'id' => $eintrag['site']->id,
                    'name' => $eintrag['site']->name,
                    'spalten' => collect($eintrag['columns'])->map(fn ($spalte) => [
                        // null: Das Gateway dieses VLANs ist nicht
                        // dokumentiert. Der Browser zeichnet dann eine
                        // Spalte ohne Kopf.
                        'gateway' => $this->knoten($spalte['gateway']),
                        'uplinks' => collect($spalte['uplinks'])->map(fn ($u) => $this->knoten($u))->values(),
                        'netze' => collect($spalte['networks'])->map(fn ($netz) => [
                            'netz' => $this->netz($netz['network']),
                            'gruppen' => collect($netz['groups'])->map(fn ($gruppe) => [
                                'titel' => __($gruppe['label']),
                                'geraete' => collect($gruppe['devices'])->map(fn ($g) => $this->knoten($g))->values(),
                            ])->values(),
                        ])->values(),
                    ])->values(),
                    // Firewall oder Router ohne alles darueber und darunter:
                    // aufgezaehlt, nicht gezeichnet.
                    'einzeln' => collect($eintrag['standalone'])->map(fn ($g) => $this->knoten($g))->values(),
                    // Anschluesse, die sich keinem Geraet zuordnen liessen -
                    // lieber offen gelassen als geraten.
                    'uplinks_ohne_geraet' => collect($eintrag['unlinkedUplinks'])->map(fn ($u) => $this->knoten($u))->values(),
                    // Geraete mit einer Adresse ausserhalb jedes VLANs -
                    // meist ein VLAN, das noch niemand dokumentiert hat.
                    'ohne_vlan' => collect($eintrag['unassigned'])->map(fn ($g) => $this->knoten($g))->values(),
                ])
                ->values(),
        ]);
    }

    /** Ein Knoten ohne die Browser-Adresse. */
    private function knoten(?array $knoten): ?array
    {
        if ($knoten === null) {
            return null;
        }

        return [
            'name' => $knoten['name'],
            'art' => isset($knoten['type']) ? __($knoten['type']) : null,
            'beisatz' => $knoten['detail'] ?: null,
            'adresse' => $knoten['address'] ?? null,
            'typ' => $knoten['typ'] ?? null,
            'id' => $knoten['id'] ?? null,
        ];
    }

    /** Das VLAN, wie es die App schon aus dem IPAM kennt. */
    private function netz(Network $netz): array
    {
        return [
            'id' => $netz->id,
            'name' => $netz->anzeige() ?: __('VLAN'),
            'adresse' => $netz->network && $netz->cidr ? $netz->network.'/'.$netz->cidr : $netz->network,
            'gateway' => $netz->gateway,
        ];
    }
}
