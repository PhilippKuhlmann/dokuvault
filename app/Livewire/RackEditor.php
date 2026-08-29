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

    /** Dokumentiertes Gerät einbauen. $typeKey wird gegen die Config aufgelöst — nie Klassennamen vom Client. */
    public function placeDevice(string $typeKey, int $deviceId, int $position): void
    {
        $rack = $this->rack();

        $types = config('custom.rack_device_types');
        if (! isset($types[$typeKey])) {
            $this->fail(__('Unbekannter Gerätetyp.'));
        }
        [$class] = $types[$typeKey];

        $device = $class::findOrFail($deviceId);
        abort_if($device->customer_id !== $rack->customer_id, 403);

        // Serverseitig pruefen, nicht nur in der Auswahlliste: Der Aufruf
        // kommt vom Client und laesst sich mit beliebiger ID nachbilden.
        if (method_exists($device, 'istRackServer') && ! $device->istRackServer()) {
            $this->fail($device->name.__(' ist ein Standserver und lässt sich nicht einbauen.'));
        }

        if (RackItem::where('device_type', $class)->where('device_id', $device->id)->exists()) {
            $this->fail($device->name.__(' ist bereits in einem Rack verbaut.'));
        }

        // Hoehe vom Geraet uebernehmen, wenn es eine kennt (z. B. ein
        // 48er-Patchfeld mit 2 HE). Sonst bleibt es bei einer Hoeheneinheit,
        // die sich im Editor per + korrigieren laesst.
        $he = (int) ($device->height_units ?? 1) ?: 1;

        $this->assertFree($rack, $this->side, $position, $he);

        $rack->items()->create([
            'side' => $this->side,
            'position' => $position,
            'height_units' => $he,
            // Tiefe beim Einbau kopieren, wie Name und Darstellung: Eine spaetere
            // Aenderung am Geraet soll den Schrank nicht rueckwirkend umbauen.
            'full_depth' => (bool) ($device->full_depth ?? true),
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

        $this->assertFree($rack, $this->side, $position, $catalogItem->height_units);

        $rack->items()->create([
            'side' => $this->side,
            'position' => $position,
            'height_units' => $catalogItem->height_units,
            'full_depth' => (bool) $catalogItem->full_depth,
            'name' => $catalogItem->name,
            'appearance' => $catalogItem->appearance,
            // Nur fuer das hinterlegte Foto - siehe RackItem::catalogItem().
            'rack_catalog_item_id' => $catalogItem->id,
        ]);
    }

    /** Einbau an eine neue Position verschieben. */
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

    public function move(int $itemId, int $newPosition): void
    {
        $rack = $this->rack();
        $item = $rack->items()->findOrFail($itemId);

        $this->nurEigeneSeite($item);

        $this->assertFree($rack, $item->side, $newPosition, $item->height_units, ignoreId: $item->id);

        $item->update(['position' => $newPosition]);
    }

    /** Höhe eines Einbaus ändern (wächst nach oben). */
    public function setHeight(int $itemId, int $he): void
    {
        $rack = $this->rack();
        $item = $rack->items()->findOrFail($itemId);

        $this->nurEigeneSeite($item);

        if ($he < 1 || $he > 8) {
            $this->fail(__('Höhe muss zwischen 1 und 8 HE liegen.'));
        }

        $this->assertFree($rack, $item->side, $item->position, $he, ignoreId: $item->id);

        $item->update(['height_units' => $he]);
    }

    public function remove(int $itemId): void
    {
        $rack = $this->rack();
        $item = $rack->items()->findOrFail($itemId);

        $this->nurEigeneSeite($item);

        $item->delete();
    }

    /**
     * Kollisionsprüfung: [position, position+he-1] muss im Rack liegen und darf
     * keinen anderen Einbau schneiden.
     */
    protected function assertFree(Rack $rack, string $seite, int $position, int $he, ?int $ignoreId = null): void
    {
        $top = $position + $he - 1;

        if ($position < 1 || $top > $rack->height_units) {
            $this->fail("Passt nicht: HE {$position}–{$top} liegt außerhalb des Racks (1–{$rack->height_units}).");
        }

        // Nur was diese Seite belegt: ein Geraet in halber Tiefe auf der
        // Gegenseite laesst hier Platz, eines in voller Tiefe nicht.
        $conflict = $rack->items()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->get()
            ->filter(fn (RackItem $item) => $item->belegtSeite($seite))
            ->first(fn (RackItem $item) => $position <= $item->topUnit() && $top >= $item->position);

        if ($conflict) {
            $hinweis = $conflict->side === $seite
                ? ''
                : ' – es reicht in voller Tiefe von der '.__(Rack::SEITEN[$conflict->side]).' durch';

            $this->fail("HE {$position}–{$top} kollidiert mit ".$conflict->label()." (HE {$conflict->position}–{$conflict->topUnit()}){$hinweis}.");
        }
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['rack' => $message]);
    }

    /** Unterste freie Position, an der ein Element mit $he Höhe Platz hat (für den Einbauen-Knopf). */
    protected function lowestFree(Rack $rack, string $seite, int $he): ?int
    {
        $occupied = [];
        foreach ($rack->itemsFuerSeite($seite) as $item) {
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

        // Freien Platz fuer die *tatsaechliche* Hoehe suchen - sonst laege ein
        // 2-HE-Patchfeld auf einer einzelnen Luecke und placeDevice lehnt ab.
        $types = config('custom.rack_device_types');
        $he = 1;
        if (isset($types[$typeKey])) {
            [$class] = $types[$typeKey];
            $he = (int) ($class::find($deviceId)?->height_units ?? 1) ?: 1;
        }

        $position = $this->lowestFree($rack, $this->side, $he) ?? $this->fail('Kein freier Platz im Rack.');
        $this->placeDevice($typeKey, $deviceId, $position);
    }

    /** Einbauen-Knopf: Katalogelement auf die unterste freie passende HE setzen. */
    public function quickPlaceCatalog(int $catalogItemId): void
    {
        $rack = $this->rack();
        $he = RackCatalogItem::find($catalogItemId)?->height_units
            ?? $this->fail('Unbekanntes Katalogelement.');
        $position = $this->lowestFree($rack, $this->side, $he) ?? $this->fail('Kein freier Platz im Rack.');
        $this->placeCatalog($catalogItemId, $position);
    }

    public function render()
    {
        $rack = $this->rack()->load('items.device', 'items.catalogItem');

        // Palette: je Typ die noch nicht verbauten Geräte des Kunden.
        // Bewusst OHNE Standortfilter aus der Session: Racks stehen an einem
        // Standort, aber verbaut wird, was der Kunde hat.
        $palette = collect(config('custom.rack_device_types'))
            ->map(function (array $entry, string $key) {
                [$class, $label] = $entry;

                // Modelle koennen selbst bestimmen, was einbaubar ist -
                // ein Standserver steht neben dem Schrank, nicht darin.
                $query = method_exists($class, 'scopeRackMountable')
                    ? $class::rackMountable()
                    : $class::query();

                $devices = $query->where('customer_id', $this->customerId)
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
