<?php

namespace App\Livewire;

use App\Livewire\Concerns\PrueftWaehrendDerEingabe;
use App\Models\NetworkSwitch;
use App\Models\PatchPanel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
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
    use PrueftWaehrendDerEingabe;

    // Skalare statt Model-Instanz: robust bei Livewire-Hydration (wie DeviceIpAddresses).
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
     * Eine Quelle fuer das Speichern und fuer die Pruefung waehrend der
     * Eingabe. Die Schluessel tragen Platzhalter - ein Patchfeld hat viele
     * Ports, und "outlet.7" faellt unter "outlet.*".
     */
    protected function regeln(): array
    {
        return [
            // Schreibweise ist je Kunde verschieden ("EG 1.01", "A.12", "2.23") -
            // deshalb kein Format erzwingen, nur die Laenge begrenzen.
            'outlet.*' => 'nullable|string|max:50',
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

        $this->pruefungEinschalten();

        $this->validate($this->regeln(), [], $this->fehlerNamen($ports));

        foreach ($ports as $id => $port) {
            $port->update([
                'outlet' => $this->leerZuNull($this->outlet[$id] ?? null),
                'label' => $this->leerZuNull($this->label[$id] ?? null),
                'network_switch_id' => $this->leerZuNull($this->switchId[$id] ?? null),
                'switch_port' => $this->leerZuNull($this->switchPort[$id] ?? null),
                'note' => $this->leerZuNull($this->note[$id] ?? null),
            ]);
        }

        $this->saved = true;
    }

    /** Eine Zeile leeren, ohne alle vier Felder einzeln loeschen zu muessen. */
    /**
     * Zaehlt die Dosennummer der ersten belegten Zeile fuer alle folgenden
     * Ports hoch: aus "1.01" wird 1.02, 1.03 ... Fuehrende Nullen und das
     * Praefix ("1.", "A-", "EG") bleiben erhalten.
     *
     * Ueberschrieben wird nichts: Nur leere Felder werden gefuellt, damit eine
     * abweichend beschriftete Dose an Port 10 stehen bleibt. Wer sie doch neu
     * durchzaehlen will, leert sie vorher.
     *
     * Gefuellt wird nur das Formular - gespeichert wird erst mit "Speichern".
     */
    public function durchnummerieren(): void
    {
        $ports = $this->panel()->ports()->orderBy('number')->get();

        $start = $ports->first(fn ($port) => filled($this->outlet[$port->id] ?? null));

        if (! $start) {
            $this->addError('outlet.'.($ports->first()?->id ?? 0), __('Bitte zuerst eine Dosennummer eintragen.'));

            return;
        }

        // Ziffernblock am Ende: "1.01" -> Praefix "1." und Zaehler "01".
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
     * Leert alle Dosennummern im Formular - fuer den Fall, dass man sich beim
     * ersten Feld vertippt hat und neu durchzaehlen will.
     *
     * Nur das Formular, nicht die Datenbank: Ein Fehlklick ist folgenlos,
     * solange nicht gespeichert wird. Raum, Switch und Notiz bleiben stehen,
     * die haengen nicht an der Nummerierung.
     */
    public function dosenLeeren(): void
    {
        $this->resetErrorBag();

        foreach (array_keys($this->outlet) as $id) {
            $this->outlet[$id] = null;
        }
    }

    public function clearPort(int $portId): void
    {
        $panel = $this->panel();
        $panel->ports()->whereKey($portId)->update([
            'outlet' => null, 'label' => null,
            'network_switch_id' => null, 'switch_port' => null, 'note' => null,
        ]);

        $this->outlet[$portId] = null;
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
            $namen["outlet.$id"] = 'Dosennummer an Port '.$port->number;
            $namen["label.$id"] = 'Raum an Port '.$port->number;
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
