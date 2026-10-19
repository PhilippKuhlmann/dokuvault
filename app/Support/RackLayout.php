<?php

namespace App\Support;

use App\Models\DeviceModel;
use App\Models\Rack;
use App\Models\RackCatalogItem;
use App\Models\RackItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Fitting out a rack: placing, moving, resizing and removing items.
 *
 * Shared by the rack editor in the browser and the iOS app, so both apply the
 * same collision rules. Permission and customer checks stay with the caller -
 * this class only knows racks and height units.
 */
class RackLayout
{
    /**
     * A documented device. $typeKey is resolved against the config - never a
     * class name from the client. Without a position: the lowest free one.
     */
    public function placeDevice(Rack $rack, string $side, string $typeKey, int $deviceId, ?int $position = null): RackItem
    {
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

        [$he, $volleTiefe] = $this->dimensions($device);

        // Freien Platz fuer die *tatsaechliche* Hoehe suchen - sonst laege ein
        // 2-HE-Patchfeld auf einer einzelnen Luecke.
        $position ??= $this->lowestFree($rack, $side, $he) ?? $this->fail('Kein freier Platz im Rack.');

        $this->assertFree($rack, $side, $position, $he);

        return $rack->items()->create([
            'side' => $side,
            'position' => $position,
            'height_units' => $he,
            // Tiefe beim Einbau kopieren, wie Name und Darstellung: Eine spaetere
            // Aenderung am Geraet soll den Schrank nicht rueckwirkend umbauen.
            'full_depth' => $volleTiefe,
            'device_type' => $class,
            'device_id' => $device->id,
        ]);
    }

    /**
     * Passives Katalogelement einbauen. Die Bezeichnung wird kopiert, damit ein
     * spaeter im Adminbereich geaenderter oder geloeschter Katalogeintrag die
     * bestehende Rack-Dokumentation nicht veraendert.
     */
    public function placeCatalog(Rack $rack, string $side, int $catalogItemId, ?int $position = null): RackItem
    {
        $catalogItem = RackCatalogItem::find($catalogItemId)
            ?? $this->fail('Unbekanntes Katalogelement.');

        $position ??= $this->lowestFree($rack, $side, $catalogItem->height_units) ?? $this->fail('Kein freier Platz im Rack.');

        $this->assertFree($rack, $side, $position, $catalogItem->height_units);

        return $rack->items()->create([
            'side' => $side,
            'position' => $position,
            'height_units' => $catalogItem->height_units,
            'full_depth' => (bool) $catalogItem->full_depth,
            'name' => $catalogItem->name,
            'appearance' => $catalogItem->appearance,
            // Nur fuer das hinterlegte Foto - siehe RackItem::catalogItem().
            'rack_catalog_item_id' => $catalogItem->id,
        ]);
    }

    /** Einbau an eine neue Position verschieben, auf seiner eigenen Seite. */
    public function move(Rack $rack, RackItem $item, int $newPosition): void
    {
        $this->assertFree($rack, $item->side, $newPosition, $item->height_units, ignoreId: $item->id);

        $item->update(['position' => $newPosition]);
    }

    /** Höhe eines Einbaus ändern (wächst nach oben). */
    public function setHeight(Rack $rack, RackItem $item, int $he): void
    {
        if ($he < 1 || $he > 8) {
            $this->fail(__('Höhe muss zwischen 1 und 8 HE liegen.'));
        }

        $this->assertFree($rack, $item->side, $item->position, $he, ignoreId: $item->id);

        $item->update(['height_units' => $he]);
    }

    /**
     * Hoehe und Tiefe eines Geraets: [Hoeheneinheiten, volle Tiefe].
     *
     * Zuerst das Geraet selbst - ein Server oder ein Patchfeld fuehrt beides
     * als eigenes Feld. Kennt sein Typ die Felder nicht (Switch, NAS, Router,
     * USV, Recorder), zaehlt das Geraetemodell: Eine "APC Smart-UPS 1500" ist
     * zwei Hoeheneinheiten hoch, egal bei welchem Kunden sie steht. Erst dann
     * eine Hoeheneinheit in voller Tiefe, die sich im Editor korrigieren
     * laesst.
     */
    public function dimensions($device): array
    {
        $modell = DeviceModel::fuerGeraet($device);

        $he = (int) ($device->height_units ?? 0)
            ?: (int) ($modell->height_units ?? 0)
            ?: 1;

        $volleTiefe = $device->full_depth ?? $modell?->full_depth ?? true;

        return [$he, (bool) $volleTiefe];
    }

    /**
     * Kollisionsprüfung: [position, position+he-1] muss im Rack liegen und darf
     * keinen anderen Einbau schneiden.
     */
    public function assertFree(Rack $rack, string $seite, int $position, int $he, ?int $ignoreId = null): void
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

    /** Unterste freie Position, an der ein Element mit $he Höhe Platz hat. */
    public function lowestFree(Rack $rack, string $seite, int $he): ?int
    {
        $occupied = [];
        foreach ($rack->items()->get() as $item) {
            if (! $item->belegtSeite($seite)) {
                continue;
            }
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

    /**
     * Palette: je Typ die noch nicht verbauten Geräte des Kunden.
     * Bewusst OHNE Standortfilter: Racks stehen an einem Standort, aber
     * verbaut wird, was der Kunde hat.
     */
    public function palette(int $customerId): Collection
    {
        return collect(config('custom.rack_device_types'))
            ->map(function (array $entry, string $key) use ($customerId) {
                [$class, $label] = $entry;

                // Modelle koennen selbst bestimmen, was einbaubar ist -
                // ein Standserver steht neben dem Schrank, nicht darin.
                $query = method_exists($class, 'scopeRackMountable')
                    ? $class::rackMountable()
                    : $class::query();

                $devices = $query->where('customer_id', $customerId)
                    ->whereNotExists(function ($query) use ($class) {
                        $query->selectRaw('1')
                            ->from('rack_items')
                            ->where('rack_items.device_type', $class)
                            ->whereColumn('rack_items.device_id', (new $class)->getTable().'.id');
                    })
                    ->orderBy('name')
                    // All columns: rackName() of a phone system without a name
                    // needs make and model.
                    ->get();

                return ['key' => $key, 'label' => $label, 'devices' => $devices];
            })
            ->filter(fn (array $group) => $group['devices']->isNotEmpty())
            ->values();
    }

    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['rack' => $message]);
    }
}
