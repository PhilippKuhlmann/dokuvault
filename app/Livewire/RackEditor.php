<?php

namespace App\Livewire;

use App\Models\Rack;
use App\Models\RackCatalogItem;
use App\Models\RackItem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Bestückung eines Racks: dokumentierte Geräte und Katalogelemente per
 * Drag & Drop (oder Knopf) einbauen, verschieben, entfernen.
 */
class RackEditor extends Component
{
    // Skalare statt Model-Instanzen: robust bei Livewire-Hydration (wie DeviceIpAddresses).
    public int $rackId;

    public int $customerId;

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

    /** Dokumentiertes Gerät einbauen. $typeKey wird gegen die Config aufgelöst — nie Klassennamen vom Client. */
    public function placeDevice(string $typeKey, int $deviceId, int $position): void
    {
        $rack = $this->rack();

        $types = config('custom.rack_device_types');
        if (! isset($types[$typeKey])) {
            $this->fail('Unbekannter Gerätetyp.');
        }
        [$class] = $types[$typeKey];

        $device = $class::findOrFail($deviceId);
        abort_if($device->customer_id !== $rack->customer_id, 403);

        if (RackItem::where('device_type', $class)->where('device_id', $device->id)->exists()) {
            $this->fail($device->name.' ist bereits in einem Rack verbaut.');
        }

        $this->assertFree($rack, $position, 1);

        $rack->items()->create([
            'position' => $position,
            'height_units' => 1,
            'device_type' => $class,
            'device_id' => $device->id,
        ]);
    }

    /**
     * Passives Katalogelement einbauen. Die Bezeichnung wird kopiert, damit ein
     * spaeter im Adminbereich geaenderter oder geloeschter Katalogeintrag die
     * bestehende Rack-Dokumentation nicht veraendert.
     */
    public function placeCatalog(int $catalogItemId, int $position): void
    {
        $rack = $this->rack();

        $catalogItem = RackCatalogItem::find($catalogItemId)
            ?? $this->fail('Unbekanntes Katalogelement.');

        $this->assertFree($rack, $position, $catalogItem->height_units);

        $rack->items()->create([
            'position' => $position,
            'height_units' => $catalogItem->height_units,
            'name' => $catalogItem->name,
        ]);
    }

    /** Einbau an eine neue Position verschieben. */
    public function move(int $itemId, int $newPosition): void
    {
        $rack = $this->rack();
        $item = $rack->items()->findOrFail($itemId);

        $this->assertFree($rack, $newPosition, $item->height_units, ignoreId: $item->id);

        $item->update(['position' => $newPosition]);
    }

    /** Höhe eines Einbaus ändern (wächst nach oben). */
    public function setHeight(int $itemId, int $he): void
    {
        $rack = $this->rack();
        $item = $rack->items()->findOrFail($itemId);

        if ($he < 1 || $he > 8) {
            $this->fail('Höhe muss zwischen 1 und 8 HE liegen.');
        }

        $this->assertFree($rack, $item->position, $he, ignoreId: $item->id);

        $item->update(['height_units' => $he]);
    }

    public function remove(int $itemId): void
    {
        $rack = $this->rack();

        $rack->items()->whereKey($itemId)->delete();
    }

    /**
     * Kollisionsprüfung: [position, position+he-1] muss im Rack liegen und darf
     * keinen anderen Einbau schneiden.
     */
    protected function assertFree(Rack $rack, int $position, int $he, ?int $ignoreId = null): void
    {
        $top = $position + $he - 1;

        if ($position < 1 || $top > $rack->height_units) {
            $this->fail("Passt nicht: HE {$position}–{$top} liegt außerhalb des Racks (1–{$rack->height_units}).");
        }

        $conflict = $rack->items()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->get()
            ->first(fn (RackItem $item) => $position <= $item->topUnit() && $top >= $item->position);

        if ($conflict) {
            $this->fail("HE {$position}–{$top} kollidiert mit ".$conflict->label()." (HE {$conflict->position}–{$conflict->topUnit()}).");
        }
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['rack' => $message]);
    }

    /** Unterste freie Position, an der ein Element mit $he Höhe Platz hat (für den Einbauen-Knopf). */
    protected function lowestFree(Rack $rack, int $he): ?int
    {
        $occupied = [];
        foreach ($rack->items as $item) {
            for ($u = $item->position; $u <= $item->topUnit(); $u++) {
                $occupied[$u] = true;
            }
        }

        for ($pos = 1; $pos + $he - 1 <= $rack->height_units; $pos++) {
            $free = true;
            for ($u = $pos; $u < $pos + $he; $u++) {
                if ($occupied[$u] ?? false) {
                    $free = false;
                    break;
                }
            }
            if ($free) {
                return $pos;
            }
        }

        return null;
    }

    /** Einbauen-Knopf: Gerät auf die unterste freie HE setzen. */
    public function quickPlaceDevice(string $typeKey, int $deviceId): void
    {
        $rack = $this->rack();
        $position = $this->lowestFree($rack, 1) ?? $this->fail('Kein freier Platz im Rack.');
        $this->placeDevice($typeKey, $deviceId, $position);
    }

    /** Einbauen-Knopf: Katalogelement auf die unterste freie passende HE setzen. */
    public function quickPlaceCatalog(int $catalogItemId): void
    {
        $rack = $this->rack();
        $he = RackCatalogItem::find($catalogItemId)?->height_units
            ?? $this->fail('Unbekanntes Katalogelement.');
        $position = $this->lowestFree($rack, $he) ?? $this->fail('Kein freier Platz im Rack.');
        $this->placeCatalog($catalogItemId, $position);
    }

    public function render()
    {
        $rack = $this->rack()->load('items.device');

        // Palette: je Typ die noch nicht verbauten Geräte des Kunden.
        // Bewusst OHNE Standortfilter aus der Session: Racks stehen an einem
        // Standort, aber verbaut wird, was der Kunde hat.
        $palette = collect(config('custom.rack_device_types'))
            ->map(function (array $entry, string $key) {
                [$class, $label] = $entry;

                $devices = $class::where('customer_id', $this->customerId)
                    ->whereNotExists(function ($query) use ($class) {
                        $query->selectRaw('1')
                            ->from('rack_items')
                            ->where('rack_items.device_type', $class)
                            ->whereColumn('rack_items.device_id', (new $class)->getTable().'.id');
                    })
                    ->orderBy('name')
                    ->get(['id', 'name']);

                return ['key' => $key, 'label' => $label, 'devices' => $devices];
            })
            ->filter(fn (array $group) => $group['devices']->isNotEmpty())
            ->values();

        return view('livewire.rack-editor', [
            'rack' => $rack,
            'palette' => $palette,
            'catalog' => RackCatalogItem::ordered()->get(),
        ]);
    }
}
