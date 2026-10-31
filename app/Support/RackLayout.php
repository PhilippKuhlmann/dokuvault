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

        // Checked on the server, not only in the select list: the call comes
        // from the client and can be replayed with any id.
        if (method_exists($device, 'istRackServer') && ! $device->istRackServer()) {
            $this->fail($device->name.__(' ist ein Standserver und lässt sich nicht einbauen.'));
        }

        if (RackItem::where('device_type', $class)->where('device_id', $device->id)->exists()) {
            $this->fail($device->name.__(' ist bereits in einem Rack verbaut.'));
        }

        [$he, $volleTiefe] = $this->dimensions($device);

        // Look for free space for the *actual* height - otherwise a device
        // 2-HE-Patchfeld auf einer einzelnen Luecke.
        $position ??= $this->lowestFree($rack, $side, $he) ?? $this->fail('Kein freier Platz im Rack.');

        $this->assertFree($rack, $side, $position, $he);

        return $rack->items()->create([
            'side' => $side,
            'position' => $position,
            'height_units' => $he,
            // Copy the depth on mounting, like name and appearance: a later
            // change on the device must not rebuild the rack retroactively.
            'full_depth' => $volleTiefe,
            'device_type' => $class,
            'device_id' => $device->id,
        ]);
    }

    /**
     * Mount a passive catalog item. The label is copied so that a catalog entry
     * later changed or deleted in the admin area does not alter the existing
     * rack documentation.
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
            // Only for the stored photo - see RackItem::catalogItem().
            'rack_catalog_item_id' => $catalogItem->id,
        ]);
    }

    /** Move a mounted item to a new position, on its own side. */
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
     * Height and depth of a device: [height units, full depth].
     *
     * The device itself first - a server or a patch panel carries both as
     * fields of its own. If its kind does not know the fields (switch, NAS,
     * router, UPS, recorder), the device model counts: an "APC Smart-UPS 1500"
     * is two height units tall, whichever customer it stands at. Only then
     * one height unit at full depth, which can be corrected in the editor.
     */
    public function dimensions($device): array
    {
        $modell = DeviceModel::forDevice($device);

        $he = (int) ($device->height_units ?? 0)
            ?: (int) ($modell->height_units ?? 0)
            ?: 1;

        $volleTiefe = $device->full_depth ?? $modell?->full_depth ?? true;

        return [$he, (bool) $volleTiefe];
    }

    /**
     * Collision check: [position, position+he-1] must lie inside the rack and must
     * keinen anderen Einbau schneiden.
     */
    public function assertFree(Rack $rack, string $seite, int $position, int $he, ?int $ignoreId = null): void
    {
        $top = $position + $he - 1;

        if ($position < 1 || $top > $rack->height_units) {
            $this->fail("Passt nicht: HE {$position}–{$top} liegt außerhalb des Racks (1–{$rack->height_units}).");
        }

        // Only what this side occupies: a device at half depth on the other
        // side leaves room here, one at full depth does not.
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

    /** The lowest free position where an item of height $he fits. */
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
     * Palette: per kind the customer's devices not yet mounted.
     * Deliberately WITHOUT a site filter: racks stand at a site, but what
     * gets mounted is whatever the customer has.
     */
    public function palette(int $customerId): Collection
    {
        return collect(config('custom.rack_device_types'))
            ->map(function (array $entry, string $key) use ($customerId) {
                [$class, $label] = $entry;

                // Models can decide for themselves what is mountable -
                // a tower server stands beside the rack, not inside it.
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
