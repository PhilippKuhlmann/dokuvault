<?php

namespace App\Livewire;

use App\Models\Rack;
use App\Models\RackCatalogItem;
use App\Models\RackItem;
use App\Support\RackLayout;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Populating a rack: documented devices and catalog items mounted, moved and
 * removed by drag and drop (or by button).
 *
 * The rules themselves sit in RackLayout - the iOS app mounts through the same ones.
 */
class RackEditor extends Component
{
    // Scalars instead of model instances: robust with Livewire hydration (like DeviceIpAddresses).
    #[Locked]
    public int $rackId;

    #[Locked]
    public int $customerId;

    /** Which side is being edited - 'front' or 'rear'. */
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
     * Reloads the rack and checks permission and tenant on EVERY action -
     * the public props can be manipulated on the client.
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

    /** Mount a documented device. $typeKey is resolved against the config - never a class name. */
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
     * Items of the other side appear as ghosts when they run through at full
     * depth. Nothing can be edited there - one would otherwise move something
     * from behind that is seen from the front.
     */
    protected function nurEigeneSeite(RackItem $item): void
    {
        if ($item->side !== $this->side) {
            $this->fail($item->label().' steht auf der '.__(Rack::SEITEN[$item->side]).' und lässt sich nur dort bearbeiten.');
        }
    }

    /** Move a mounted item to a new position. */
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

    /** Mount button: put the device on the lowest free U. */
    public function quickPlaceDevice(string $typeKey, int $deviceId): void
    {
        $this->layout()->placeDevice($this->rack(), $this->side, $typeKey, $deviceId);
    }

    /** Mount button: put the catalog item on the lowest free fitting U. */
    public function quickPlaceCatalog(int $catalogItemId): void
    {
        $this->layout()->placeCatalog($this->rack(), $this->side, $catalogItemId);
    }

    public function render()
    {
        $rack = $this->rack()->load('items.device', 'items.catalogItem');

        return view('livewire.rack-editor', [
            'rack' => $rack,
            // Only device kinds the role may see; the palette lists names.
            'palette' => $this->layout()->palette($this->customerId)
                ->filter(fn (array $gruppe) => $this->darfSehen($gruppe['key']))
                ->values(),
            'catalog' => RackCatalogItem::ordered()->get(),
        ]);
    }

    private function darfSehen(string $schluessel): bool
    {
        $klasse = config('custom.rack_device_types')[$schluessel][0];
        $recht = strtolower(class_basename($klasse)).'_viewAny';

        // A kind without a right of its own (patch panel) stays visible.
        return ! Gate::has($recht) || Gate::allows($recht);
    }
}
