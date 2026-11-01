<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\RackRequest;
use App\Models\Customer;
use App\Models\Rack;
use App\Models\RackCatalogItem;
use App\Models\RackItem;
use App\Support\MobileObjects;
use App\Support\RackLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Racks in the iOS app: view the layout, create and edit racks, fit them out.
 *
 * The list comes from DatenController (type "rack"). Every route sits below
 * the customer, so isCustomer and the scoped bindings keep a customer user to
 * their own racks - the ids come from the phone. The fitting-out rules are
 * the browser's, see RackLayout.
 */
class RackController extends Controller
{
    public function __construct(private RackLayout $layout, private MobileObjects $objekte) {}

    public function show(Request $request, Customer $customer, Rack $rack): JsonResponse
    {
        Gate::authorize('rack_viewAny');

        return response()->json($this->rack($rack, $request));
    }

    public function store(RackRequest $request, Customer $customer): JsonResponse
    {
        Gate::authorize('rack_create');

        $rack = $customer->racks()->create($request->validated());

        return response()->json($this->rack($rack, $request), 201);
    }

    public function update(RackRequest $request, Customer $customer, Rack $rack): JsonResponse
    {
        Gate::authorize('rack_update');

        $data = $request->validated();

        // Verkleinern nur, solange oben kein Einbau herausragen wuerde.
        $highest = $rack->items()->get()->max(fn ($item) => $item->topUnit()) ?? 0;
        if ($data['height_units'] < $highest) {
            $meldung = "Das Rack kann nicht auf {$data['height_units']} HE verkleinert werden – der oberste Einbau reicht bis HE {$highest}.";

            return response()->json(['message' => $meldung, 'errors' => ['height_units' => [$meldung]]], 422);
        }

        $rack->update($data);

        return response()->json($this->rack($rack, $request));
    }

    public function destroy(Customer $customer, Rack $rack): JsonResponse
    {
        Gate::authorize('rack_delete');

        $rack->delete();

        return response()->json(null, 204);
    }

    /** What can still be fitted: the customer's free devices and the catalogue. */
    public function palette(Customer $customer, Rack $rack): JsonResponse
    {
        Gate::authorize('rack_update');

        return response()->json([
            'devices' => $this->layout->palette($customer->id)->map(fn ($gruppe) => [
                'type' => $gruppe['key'],
                'title' => __($gruppe['label']),
                'entries' => $gruppe['devices']->map(fn ($geraet) => [
                    'id' => $geraet->getKey(),
                    'name' => method_exists($geraet, 'rackName') ? $geraet->rackName() : $geraet->name,
                    'height' => $this->layout->dimensions($geraet)[0],
                ])->values(),
            ]),
            'catalog' => RackCatalogItem::ordered()->get()->map(fn ($eintrag) => [
                'id' => $eintrag->id,
                'name' => $eintrag->name,
                'height' => $eintrag->height_units,
                'full_depth' => (bool) $eintrag->full_depth,
                'appearance' => $eintrag->appearance,
            ]),
        ]);
    }

    /**
     * Fit a device ("type" + "id") or a catalogue item ("catalog_id").
     * Without a position it goes to the lowest free height unit.
     */
    public function storeItem(Request $request, Customer $customer, Rack $rack): JsonResponse
    {
        Gate::authorize('rack_update');

        $data = $request->validate([
            'side' => ['required', Rule::in(array_keys(Rack::SEITEN))],
            'position' => 'nullable|integer|min:1',
            'type' => ['required_without:catalog_id', 'nullable', Rule::in(array_keys(config('custom.rack_device_types')))],
            'id' => 'required_with:type|nullable|integer',
            'catalog_id' => 'required_without:type|nullable|integer',
        ]);

        $item = isset($data['type'])
            ? $this->layout->placeDevice($rack, $data['side'], $data['type'], $data['id'], $data['position'] ?? null)
            : $this->layout->placeCatalog($rack, $data['side'], $data['catalog_id'], $data['position'] ?? null);

        return response()->json($this->mount($item->fresh()), 201);
    }

    /** Move and/or resize - on the side the item is fitted on. */
    public function updateItem(Request $request, Customer $customer, Rack $rack, RackItem $item): JsonResponse
    {
        Gate::authorize('rack_update');

        $data = $request->validate([
            'position' => 'sometimes|integer|min:1',
            'height' => 'sometimes|integer|between:1,8',
        ]);

        if (isset($data['height'])) {
            $this->layout->setHeight($rack, $item, $data['height']);
        }

        if (isset($data['position'])) {
            $this->layout->move($rack, $item, $data['position']);
        }

        return response()->json($this->mount($item->fresh()));
    }

    public function destroyItem(Customer $customer, Rack $rack, RackItem $item): JsonResponse
    {
        Gate::authorize('rack_update');

        $item->delete();

        return response()->json(null, 204);
    }

    private function rack(Rack $rack, Request $request): array
    {
        $rack->load('site', 'customer', 'items.device');

        return [
            'id' => $rack->id,
            'name' => $rack->name,
            'height' => $rack->height_units,
            'location' => $rack->location,
            'note' => $rack->note,
            'site' => $rack->site ? ['id' => $rack->site->id, 'name' => $rack->site->name] : null,
            'customer' => $this->objekte->kunde($rack->customer),
            'may_update' => Gate::forUser($request->user())->allows('rack_update'),
            'may_delete' => Gate::forUser($request->user())->allows('rack_delete'),
            'sides' => collect(Rack::SEITEN)->map(fn ($titel, $seite) => ['side' => $seite, 'title' => __($titel)])->values(),
            // Highest first, as in the front view. A full-depth item occupies
            // the other side as well, see RackItem::belegtSeite().
            'mounts' => $rack->items->map(fn ($item) => $this->mount($item))->values(),
        ];
    }

    private function mount(RackItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->label(),
            'side' => $item->side,
            'position' => $item->position,
            'height' => $item->height_units,
            'top_unit' => $item->topUnit(),
            'full_depth' => $item->full_depth,
            'appearance' => $item->faceAppearance(),
            // The device behind it, to open with /objects/{typ}/{id}.
            'device' => $item->device_type ? [
                'type' => collect(config('custom.rack_device_types'))->search(fn ($eintrag) => $eintrag[0] === $item->device_type) ?: null,
                'id' => $item->device_id,
            ] : null,
        ];
    }
}
