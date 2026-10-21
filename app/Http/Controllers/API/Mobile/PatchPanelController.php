<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatchPanelRequest;
use App\Models\Customer;
use App\Models\NetworkSwitch;
use App\Models\PatchPanel;
use App\Support\MobilObjekte;
use App\Support\PatchPortEditing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Patch panels in the iOS app: create, edit, delete and label the ports.
 *
 * Like the rack, a patch panel has no form in config/forms.php - its ports
 * come with it. The list comes from DatenController (type "patchpanel").
 * Every route sits below the customer with scoped bindings, so a panel of
 * another customer is a 404. The rules are the browser's (PatchPanelRequest,
 * PatchPortEditing).
 */
class PatchPanelController extends Controller
{
    public function __construct(private MobilObjekte $objekte) {}

    public function show(Request $request, Customer $customer, PatchPanel $patchpanel): JsonResponse
    {
        Gate::authorize('patchpanel_viewAny');

        return response()->json($this->patchfeld($patchpanel, $request));
    }

    public function store(PatchPanelRequest $request, Customer $customer): JsonResponse
    {
        Gate::authorize('patchpanel_create');

        $patchpanel = $customer->patchpanels()->create($request->validated());
        $patchpanel->syncPorts();

        return response()->json($this->patchfeld($patchpanel, $request), 201);
    }

    public function update(PatchPanelRequest $request, Customer $customer, PatchPanel $patchpanel): JsonResponse
    {
        Gate::authorize('patchpanel_update');

        $data = $request->validated();

        if ($fehler = $patchpanel->shrinkError((int) $data['port_count'])) {
            return response()->json(['message' => $fehler, 'errors' => ['port_count' => [$fehler]]], 422);
        }

        $patchpanel->update($data);
        $patchpanel->syncPorts();

        return response()->json($this->patchfeld($patchpanel->fresh(), $request));
    }

    public function destroy(Customer $customer, PatchPanel $patchpanel): JsonResponse
    {
        Gate::authorize('patchpanel_delete');

        $patchpanel->delete();

        return response()->json(null, 204);
    }

    /**
     * Label ports: {ports: [{id, outlet?, label?, network_switch_id?,
     * switch_port?, note?}]}. Only the ports and columns sent are written;
     * "" clears. A port of another panel is ignored.
     */
    public function updatePorts(Request $request, Customer $customer, PatchPanel $patchpanel, PatchPortEditing $ports): JsonResponse
    {
        Gate::authorize('patchpanel_update');

        $regeln = ['ports' => ['required', 'array'], 'ports.*.id' => ['required', 'integer']];
        foreach ($ports->rules($customer->id) as $spalte => $regel) {
            $regeln['ports.*.'.$spalte] = $regel;
        }

        $daten = Validator::make($request->all(), $regeln)->validate();

        $ports->save($patchpanel, collect($daten['ports'])->keyBy('id')->all());

        return response()->json($this->patchfeld($patchpanel->fresh(), $request));
    }

    private function patchfeld(PatchPanel $panel, Request $request): array
    {
        $panel->load('site', 'customer', 'ports.networkSwitch', 'rackItem.rack');
        $nutzer = $request->user();

        return [
            'id' => $panel->id,
            'name' => $panel->name,
            'ports_anzahl' => $panel->port_count,
            'hoehe' => $panel->height_units,
            'hersteller' => $panel->manufacturer,
            'modell' => $panel->model,
            'notiz' => $panel->note,
            'standort' => $panel->site ? ['id' => $panel->site->id, 'name' => $panel->site->name] : null,
            'kunde' => $this->objekte->kunde($panel->customer),
            'einbauort' => $panel->einbauort(),
            'darf_bearbeiten' => Gate::forUser($nutzer)->allows('patchpanel_update'),
            'darf_loeschen' => Gate::forUser($nutzer)->allows('patchpanel_delete'),
            'ports' => $panel->ports->sortBy('number')->map(fn ($port) => [
                'id' => $port->id,
                'nummer' => $port->number,
                'dose' => $port->outlet,
                'raum' => $port->label,
                'switch' => $port->networkSwitch ? ['id' => $port->networkSwitch->id, 'name' => $port->networkSwitch->name] : null,
                'switch_port' => $port->switch_port,
                'notiz' => $port->note,
            ])->values(),
            // The choices for "switch" - only this customer's.
            'switches' => NetworkSwitch::where('customer_id', $panel->customer_id)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
