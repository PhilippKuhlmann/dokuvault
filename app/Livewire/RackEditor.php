<?php

namespace App\Livewire;

use App\Models\Rack;
use App\Models\RackCatalogItem;
use App\Models\RackItem;
use App\Support\RackLayout;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Bestückung eines Racks: dokumentierte Geräte und Katalogelemente per
 * Drag & Drop (oder Knopf) einbauen, verschieben, entfernen.
 *
 * Die Regeln selbst stehen in RackLayout - die iOS-App baut ueber dieselben ein.
 */
class RackEditor extends Component
{
    // Skalare statt Model-Instanzen: robust bei Livewire-Hydration (wie DeviceIpAddresses).
    public int $rackId;

    public int $customerId;

    /** Welche Seite gerade bearbeitet wird - 'front' oder 'rear'. */
    public string $side = 'front';

    public function setSide(string $side): void
    {
        $this->side = array_key_exists($side, Rack::SEITEN) ? $side : 'front';
    }

    public function mount($rack, $customer): void
    {
        $this->rackId = $rack->id;
        $this->customerId = $customer->id;
    }

    /**
     * Lädt das Rack neu und prüft Berechtigung + Mandant bei JEDER Aktion —
     * die öffentlichen Props sind clientseitig manipulierbar.
     */
    protected function rack(): Rack
    {
        $rack = Rack::findOrFail($this->rackId);

        Gate::authorize('rack_update');

        $user = auth()->user();
        abort_if($user->customer_id && $user->customer_id !== $rack->customer_id, 403);
        abort_if($rack->customer_id !== $this->customerId, 403);

        return $rack;
    }

    protected function layout(): RackLayout
    {
        return app(RackLayout::class);
    }

    /** Dokumentiertes Gerät einbauen. $typeKey wird gegen die Config aufgelöst — nie Klassennamen vom Client. */
    public function placeDevice(string $typeKey, int $deviceId, int $position): void
    {
        $this->layout()->placeDevice($this->rack(), $this->side, $typeKey, $deviceId, $position);
    }

    /** Passives Katalogelement einbauen. */
    public function placeCatalog(int $catalogItemId, int $position): void
    {
        $this->layout()->placeCatalog($this->rack(), $this->side, $catalogItemId, $position);
    }

    /**
     * Einbauten der Gegenseite erscheinen als Geister, wenn sie in voller Tiefe
     * durchreichen. Bearbeiten laesst sich dort nichts - sonst verschoebe man
     * von hinten etwas, das man vorne sieht.
     */
    protected function nurEigeneSeite(RackItem $item): void
    {
        if ($item->side !== $this->side) {
            $this->fail($item->label().' steht auf der '.__(Rack::SEITEN[$item->side]).' und lässt sich nur dort bearbeiten.');
        }
    }

    /** Einbau an eine neue Position verschieben. */
    public function move(int $itemId, int $newPosition): void
    {
        $rack = $this->rack();
        $item = $rack->items()->findOrFail($itemId);

        $this->nurEigeneSeite($item);

        $this->layout()->move($rack, $item, $newPosition);
    }

    /** Höhe eines Einbaus ändern (wächst nach oben). */
    public function setHeight(int $itemId, int $he): void
    {
        $rack = $this->rack();
        $item = $rack->items()->findOrFail($itemId);

        $this->nurEigeneSeite($item);

        $this->layout()->setHeight($rack, $item, $he);
    }

    public function remove(int $itemId): void
    {
        $rack = $this->rack();
        $item = $rack->items()->findOrFail($itemId);

        $this->nurEigeneSeite($item);

        $item->delete();
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['rack' => $message]);
    }

    /** Einbauen-Knopf: Gerät auf die unterste freie HE setzen. */
    public function quickPlaceDevice(string $typeKey, int $deviceId): void
    {
        $this->layout()->placeDevice($this->rack(), $this->side, $typeKey, $deviceId);
    }

    /** Einbauen-Knopf: Katalogelement auf die unterste freie passende HE setzen. */
    public function quickPlaceCatalog(int $catalogItemId): void
    {
        $this->layout()->placeCatalog($this->rack(), $this->side, $catalogItemId);
    }

    public function render()
    {
        $rack = $this->rack()->load('items.device', 'items.catalogItem');

        return view('livewire.rack-editor', [
            'rack' => $rack,
            'palette' => $this->layout()->palette($this->customerId),
            'catalog' => RackCatalogItem::ordered()->get(),
        ]);
    }
}
