<?php

namespace App\Livewire;

use App\Livewire\Concerns\ValidatesOnInput;
use App\Models\NetworkSwitch;
use App\Models\PatchPanel;
use App\Support\PatchPortEditing;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Port labelling of a patch panel: which outlet hangs on which port and which
 * switch port it is patched to.
 *
 * Unlike DeviceIpAddresses nothing is added here but edited - the port rows
 * are created by the patch panel when it is created. Hence arrays per field,
 * indexed by port id, and one save button instead of 48 single requests.
 */
class PatchPanelPorts extends Component
{
    use ValidatesOnInput;

    // Scalars instead of a model instance: robust with Livewire hydration (like DeviceIpAddresses).
    #[Locked]
    public int $panelId;

    #[Locked]
    public int $customerId;

    /** @var array<int, string|null> Port-ID => Wert */
    public array $outlet = [];

    public array $label = [];

    public array $switchId = [];

    public array $switchPort = [];

    public array $note = [];

    public bool $saved = false;

    /**
     * One source for saving and for the check while typing. The keys carry
     * placeholders - a patch panel has many ports, and "outlet.7" falls under
     * "outlet.*".
     */
    protected function regeln(): array
    {
        $regeln = app(PatchPortEditing::class)->rules($this->customerId);

        return [
            'outlet.*' => $regeln['outlet'],
            'label.*' => $regeln['label'],
            'switchPort.*' => $regeln['switch_port'],
            'note.*' => $regeln['note'],
            'switchId.*' => $regeln['network_switch_id'],
        ];
    }

    public function mount(PatchPanel $panel, $customer): void
    {
        $this->panelId = $panel->id;
        $this->customerId = $customer->id;

        foreach ($panel->ports as $port) {
            $this->outlet[$port->id] = $port->outlet;
            $this->label[$port->id] = $port->label;
            $this->switchId[$port->id] = $port->network_switch_id;
            $this->switchPort[$port->id] = $port->switch_port;
            $this->note[$port->id] = $port->note;
        }
    }

    /**
     * Reload and check on every action: public properties come from the client
     * and can be manipulated.
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

        // Only ports of this patch panel - a foreign port id slipped in would
        // otherwise be writable.
        $ports = $panel->ports()->get()->keyBy('id');

        $this->enableCheck();

        $this->validate($this->regeln(), [], $this->errorNames($ports));

        app(PatchPortEditing::class)->save($panel, $ports->map(fn ($port, $id) => [
            'outlet' => $this->outlet[$id] ?? null,
            'label' => $this->label[$id] ?? null,
            'network_switch_id' => $this->switchId[$id] ?? null,
            'switch_port' => $this->switchPort[$id] ?? null,
            'note' => $this->note[$id] ?? null,
        ])->all());

        $this->saved = true;
    }

    /** Clear a row without having to empty all four fields by hand. */
    /**
     * Counts the outlet number of the first filled row up for every following
     * port: "1.01" becomes 1.02, 1.03 ... Leading zeros and the prefix
     * ("1.", "A-", "EG") are kept.
     *
     * Nothing is overwritten: only empty fields are filled, so an outlet
     * labelled differently at port 10 stays. Whoever wants to renumber it
     * anyway empties it first.
     *
     * Only the form is filled - saving happens with "save".
     */
    public function durchnummerieren(): void
    {
        $ports = $this->panel()->ports()->orderBy('number')->get();

        $start = $ports->first(fn ($port) => filled($this->outlet[$port->id] ?? null));

        if (! $start) {
            $this->addError('outlet.'.($ports->first()?->id ?? 0), __('Bitte zuerst eine Dosennummer eintragen.'));

            return;
        }

        // Digits at the end: "1.01" -> prefix "1." and counter "01".
        if (! preg_match('/^(.*?)(\d+)$/', trim((string) $this->outlet[$start->id]), $treffer)) {
            $this->addError('outlet.'.$start->id, __('Die Dosennummer muss auf eine Zahl enden.'));

            return;
        }

        [, $praefix, $zahl] = $treffer;
        $stellen = strlen($zahl);
        $zaehler = (int) $zahl;

        foreach ($ports as $port) {
            if ($port->number <= $start->number) {
                continue;
            }

            $zaehler++;

            if (blank($this->outlet[$port->id] ?? null)) {
                $this->outlet[$port->id] = $praefix.str_pad((string) $zaehler, $stellen, '0', STR_PAD_LEFT);
            }
        }
    }

    /**
     * Empties every outlet number in the form - for the case that one
     * mistyped the first field and wants to renumber.
     *
     * Only the form, not the database: a misclick has no consequence as long
     * as nothing is saved. Room, switch and note stay, they do not hang on the
     * numbering.
     */
    public function clearPorts(): void
    {
        $this->resetErrorBag();

        foreach (array_keys($this->outlet) as $id) {
            $this->outlet[$id] = null;
        }
    }

    public function clearPort(int $portId): void
    {
        $panel = $this->panel();
        app(PatchPortEditing::class)->clear($panel, $portId);

        $this->outlet[$portId] = null;
        $this->label[$portId] = null;
        $this->switchId[$portId] = null;
        $this->switchPort[$portId] = null;
        $this->note[$portId] = null;
    }

    /** Error messages should name the port number, not the array index. */
    private function errorNames($ports): array
    {
        $namen = [];
        foreach ($ports as $id => $port) {
            $namen["outlet.$id"] = 'Dosennummer an Port '.$port->number;
            $namen["label.$id"] = 'Raum an Port '.$port->number;
            $namen["switchId.$id"] = 'Switch an Port '.$port->number;
            $namen["switchPort.$id"] = 'Switch-Port an Port '.$port->number;
            $namen["note.$id"] = 'Notiz an Port '.$port->number;
        }

        return $namen;
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
