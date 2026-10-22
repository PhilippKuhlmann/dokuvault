<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\IpRange;
use App\Models\Network;
use App\Models\Site;
use App\Models\User;
use App\Support\IpPlan;
use App\Support\IpRangeEditing;
use App\Support\MobilObjekte;
use App\Support\NetworkEditing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * The IPAM in the iOS app: VLANs with their IP plan and reserved ranges.
 *
 * The browser has this on two pages - the VLAN list (NetworkQuickCreate) and
 * the IP plan (IpPlanController, IpBereiche). The app gets one list with the
 * usage per network and one detail with the plan. Same rules, same
 * computation (NetworkEditing, IpPlan, IpRangeEditing). Every route sits
 * below the customer with scoped bindings: a network of another customer is
 * a 404.
 */
class NetworkController extends Controller
{
    /** Model class => type slug the user may open, or null. Per request. */
    private array $typen = [];

    public function __construct(private MobilObjekte $objekte) {}

    /** All VLANs with their usage. ?standort= limits to one site. */
    public function index(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('network_viewAny');

        $netze = Network::where('customer_id', $customer->id)
            ->when($request->integer('standort'), fn ($q, $site) => $q->where('site_id', $site))
            ->with('site')
            ->orderBy('vlanId')
            ->get();

        $plaene = $this->plan($request->user())->plans($customer, $netze);

        return response()->json([
            'eintraege' => $plaene->map(fn ($p) => $this->netz($p['network'], $p['plan']))->values(),
            'summe' => [
                'belegt' => $plaene->sum(fn ($p) => $p['plan']['usedCount'] ?? 0),
                'adressen' => $plaene->sum(fn ($p) => $p['plan']['total'] ?? 0),
            ],
            // The choices for "site" when creating, and for the filter.
            'standorte' => Site::where('customer_id', $customer->id)->orderBy('name')->get(['id', 'name']),
            'darf_anlegen' => Gate::allows('network_create'),
        ]);
    }

    /** One VLAN with its IP plan and reserved ranges. */
    public function show(Request $request, Customer $customer, Network $network): JsonResponse
    {
        Gate::authorize('network_viewAny');

        return response()->json($this->detail($network, $request->user()));
    }

    public function store(Request $request, Customer $customer, NetworkEditing $netze): JsonResponse
    {
        Gate::authorize('network_create');

        $daten = $this->validieren($netze->completeMask($request->only(NetworkEditing::COLUMNS)), $customer, $netze);

        $network = Network::create($netze->values($daten, (int) $daten['site_id']) + ['customer_id' => $customer->id]);

        return response()->json($this->detail($network, $request->user()), 201);
    }

    /**
     * Only the fields sent change; the rest keeps its value. Sending only
     * the mask (or only the CIDR) recomputes the other one.
     */
    public function update(Request $request, Customer $customer, Network $network, NetworkEditing $netze): JsonResponse
    {
        Gate::authorize('network_update');

        $eingabe = $request->only(NetworkEditing::COLUMNS);

        if (array_key_exists('subnetmask', $eingabe) xor array_key_exists('cidr', $eingabe)) {
            $eingabe += ['subnetmask' => '', 'cidr' => null];
        }

        $daten = $this->validieren(
            $netze->completeMask($eingabe + $network->only(NetworkEditing::COLUMNS)), $customer, $netze
        );

        $network->update($netze->values($daten, (int) $daten['site_id']));

        return response()->json($this->detail($network->fresh(), $request->user()));
    }

    /** To the trash, like in the browser; linked addresses keep their network_id. */
    public function destroy(Customer $customer, Network $network): JsonResponse
    {
        Gate::authorize('network_delete');

        $network->delete();

        return response()->json(null, 204);
    }

    /** {from_ip, to_ip, label, note?} */
    public function storeRange(Request $request, Customer $customer, Network $network, IpRangeEditing $bereiche): JsonResponse
    {
        Gate::authorize('network_update');

        $daten = Validator::make($request->all(), $bereiche->rules(), [], $bereiche->attributeNames())->validate();
        $bereiche->create($network, $daten);

        return response()->json($this->detail($network, $request->user()), 201);
    }

    public function destroyRange(Customer $customer, Network $network, IpRange $ipRange, IpRangeEditing $bereiche): JsonResponse
    {
        Gate::authorize('network_update');

        $bereiche->delete($network, $ipRange->id);

        return response()->json(null, 204);
    }

    private function validieren(array $eingabe, Customer $customer, NetworkEditing $netze): array
    {
        return Validator::make($eingabe, $netze->rules($customer->id), [], $netze->attributeNames())->validate();
    }

    private function detail(Network $network, User $nutzer): array
    {
        $network->loadMissing('site', 'customer');
        $p = $this->plan($nutzer)->plans($network->customer, collect([$network]))->first();
        $plan = $p['plan'];

        return $this->netz($network, $plan) + [
            'dns1' => $network->dns1,
            'dns2' => $network->dns2,
            'dhcp_start' => $network->dhcpStart,
            'dhcp_ende' => $network->dhcpEnd,
            'naechste_freie' => $network->naechsteFreieAdresse(),
            'kunde' => $this->objekte->kunde($network->customer),
            'darf_bearbeiten' => Gate::forUser($nutzer)->allows('network_update'),
            'darf_loeschen' => Gate::forUser($nutzer)->allows('network_delete'),
            'bereiche' => $p['bereiche']->map(fn (IpRange $b) => [
                'id' => $b->id,
                'von' => $b->from_ip,
                'bis' => $b->to_ip,
                'label' => $b->label,
                'notiz' => $b->note,
                'anzahl' => $b->anzahl(),
                'farbe' => $this->farbe($b->farbe ?? []),
            ])->values(),
            'zeilen' => collect($plan['rows'])->map(fn ($z) => [
                'art' => ['device' => 'geraet', 'free' => 'frei', 'dhcp' => 'dhcp', 'reserved' => 'reserviert'][$z['kind']],
                'von' => $z['from'],
                'bis' => $z['to'],
                'label' => $z['label'],
                'gateway' => $z['isGateway'] ?? false,
                // A used address inside a reserved range: what the range is for.
                'reservierung' => $z['reservierung'] ?? null,
                'farbe' => $this->farbe($z['farbe'] ?? []),
                'geraete' => $this->geraete($z['geraete']),
            ])->values(),
            // Over 8192 hosts the plan stops - the app says so.
            'gekuerzt' => $plan['truncated'] ?? false,
            // DHCP devices of a network without a DHCP range: no row to stand at.
            'dhcp_ohne_bereich' => $this->geraete($plan['dhcpOhneBereich'] ?? []),
        ];
    }

    /** A network in list and detail: its data plus the usage. */
    private function netz(Network $network, array $plan): array
    {
        return [
            'id' => $network->id,
            'name' => $network->anzeige() ?: $network->network,
            'bezeichnung' => $network->description,
            // As numbers - the columns are text in some installations.
            'vlan' => is_numeric($network->vlanId) ? (int) $network->vlanId : null,
            'netz' => $network->network,
            'subnetzmaske' => $network->subnetmask,
            'cidr' => is_numeric($network->cidr) ? (int) $network->cidr : Network::cidrAusMaske($network->subnetmask),
            'gateway' => $network->gateway,
            'standort' => $network->site ? ['id' => $network->site->id, 'name' => $network->site->name] : null,
            // null with "fehler" when network/mask cannot be evaluated.
            'auslastung' => $plan['error'] ? null : [
                'belegt' => $plan['counts']['device'],
                'dhcp' => $plan['counts']['dhcp'],
                'reserviert' => $plan['counts']['reserved'],
                'frei' => $plan['counts']['free'],
                'gesamt' => $plan['total'],
            ],
            'fehler' => $plan['error'],
        ];
    }

    private function geraete(array $eintraege): array
    {
        return array_map(fn ($e) => [
            'name' => $e['name'],
            'typ' => $e['typ'],
            'id' => $e['id'],
            // A fixed address inside the DHCP range - possibly a conflict.
            'im_pool' => $e['imPool'] ?? false,
        ], $eintraege);
    }

    /** The colour name ("amber") from the Tailwind classes of a range. */
    private function farbe(array $klassen): ?string
    {
        return preg_match('/\bbg-([a-z]+)-\d/', $klassen['balken'] ?? '', $treffer) ? $treffer[1] : null;
    }

    /** The plan with devices as type and id - for those the user may open. */
    private function plan(User $nutzer): IpPlan
    {
        return new IpPlan(function (?Model $geraet, string $zusatz) use ($nutzer) {
            $typ = $geraet ? $this->typ($geraet::class, $nutzer) : null;

            return [
                'name' => IpPlan::geraetName($geraet).$zusatz,
                'typ' => $typ,
                'id' => $typ ? $geraet->getKey() : null,
            ];
        });
    }

    private function typ(string $klasse, User $nutzer): ?string
    {
        if (! array_key_exists($klasse, $this->typen)) {
            $this->typen[$klasse] = collect($this->objekte->sichtbareTypen($nutzer))
                ->search(fn ($typ) => $typ[0] === $klasse) ?: null;
        }

        return $this->typen[$klasse];
    }
}
