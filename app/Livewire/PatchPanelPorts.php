<?php

namespace App\Livewire;

use App\Models\NetworkSwitch;
use App\Models\PatchPanel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Portbeschriftung eines Patchfelds: welche Dose haengt an welchem Port und auf
 * welchen Switch-Port ist er gepatcht.
 *
 * Anders als DeviceIpAddresses wird hier nicht hinzugefuegt, sondern bearbeitet -
 * die Portzeilen legt das Patchfeld beim Anlegen an. Deshalb Arrays je Feld,
 * indiziert nach Port-ID, und ein Speichern-Knopf statt 48 Einzelabfragen.
 */
class PatchPanelPorts extends Component
{
    // Skalare statt Model-Instanz: robust bei Livewire-Hydration (wie DeviceIpAddresses).
    public int $panelId;

    public int $customerId;

    /** @var array<int, string|null> Port-ID => Wert */
    public array $label = [];

    public array $switchId = [];

    public array $switchPort = [];

    public array $note = [];

    public bool $saved = false;

    public function mount(PatchPanel $panel, $customer): void
    {
        $this->panelId = $panel->id;
        $this->customerId = $customer->id;

        foreach ($panel->ports as $port) {
            $this->label[$port->id] = $port->label;
            $this->switchId[$port->id] = $port->network_switch_id;
            $this->switchPort[$port->id] = $port->switch_port;
            $this->note[$port->id] = $port->note;
        }
    }

    /**
     * Bei jeder Aktion neu laden und pruefen: oeffentliche Eigenschaften kommen
     * vom Client und koennen manipuliert sein.
     */
    protected function panel(): PatchPanel
    {
        $panel = PatchPanel::findOrFail($this->panelId);

        Gate::authorize('patchpanel_update');

        $user = auth()->user();
        abort_if($user->customer_id && $user->customer_id !== $panel->customer_id, 403);
        abort_if($panel->customer_id !== $this->customerId, 403);

        return $panel;
    }

    public function save(): void
    {
        $panel = $this->panel();

        // Nur Ports dieses Patchfelds - eine untergeschobene fremde Port-ID
        // waere sonst beschreibbar.
        $ports = $panel->ports()->get()->keyBy('id');

        $this->validate([
            'label.*' => 'nullable|string|max:255',
            'switchPort.*' => 'nullable|string|max:50',
            'note.*' => 'nullable|string|max:255',
            // Kundengebunden: der Switch eines fremden Kunden wird abgelehnt.
            'switchId.*' => [
                'nullable',
                Rule::exists('network_switches', 'id')
                    ->where('customer_id', $this->customerId)
                    ->whereNull('deleted_at'),
            ],
        ], [], $this->fehlerNamen($ports));

        foreach ($ports as $id => $port) {
            $port->update([
                'label' => $this->leerZuNull($this->label[$id] ?? null),
                'network_switch_id' => $this->leerZuNull($this->switchId[$id] ?? null),
                'switch_port' => $this->leerZuNull($this->switchPort[$id] ?? null),
                'note' => $this->leerZuNull($this->note[$id] ?? null),
            ]);
        }

        $this->saved = true;
    }

    /** Eine Zeile leeren, ohne alle vier Felder einzeln loeschen zu muessen. */
    public function clearPort(int $portId): void
    {
        $panel = $this->panel();
        $panel->ports()->whereKey($portId)->update([
            'label' => null, 'network_switch_id' => null, 'switch_port' => null, 'note' => null,
        ]);

        $this->label[$portId] = null;
        $this->switchId[$portId] = null;
        $this->switchPort[$portId] = null;
        $this->note[$portId] = null;
    }

    /** Fehlermeldungen sollen die Portnummer nennen, nicht den Array-Index. */
    private function fehlerNamen($ports): array
    {
        $namen = [];
        foreach ($ports as $id => $port) {
            $namen["label.$id"] = 'Dose an Port '.$port->number;
            $namen["switchId.$id"] = 'Switch an Port '.$port->number;
            $namen["switchPort.$id"] = 'Switch-Port an Port '.$port->number;
            $namen["note.$id"] = 'Notiz an Port '.$port->number;
        }

        return $namen;
    }

    private function leerZuNull($wert)
    {
        return $wert === '' ? null : $wert;
    }

    public function render()
    {
        $panel = $this->panel();

        return view('livewire.patch-panel-ports', [
            'panel' => $panel,
            'ports' => $panel->ports()->get(),
            'switches' => NetworkSwitch::where('customer_id', $this->customerId)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
