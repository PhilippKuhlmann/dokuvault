<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A mounted item in the rack: either a linked documented device
 * (device_type/device_id) or a passive catalog item (name only).
 */
class RackItem extends Model
{
    use HasFactory;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function rack()
    {
        return $this->belongsTo(Rack::class);
    }

    public function device()
    {
        return $this->morphTo();
    }

    /**
     * The catalog entry this item came from - for its image only.
     *
     * Everything descriptive (label, appearance, height) was copied on
     * mounting and stays even when the entry disappears. With the image that
     * is not possible: the file hangs on the catalog entry. If it goes, the
     * photo goes and the drawn panel takes its place.
     */
    public function catalogItem()
    {
        return $this->belongsTo(RackCatalogItem::class, 'rack_catalog_item_id');
    }

    /**
     * The device model behind a documented item, or null.
     *
     * Found through the device's manufacturer and model, not through a
     * reference column - see DeviceModel.
     */
    public function deviceModel(): ?DeviceModel
    {
        return DeviceModel::forDevice($this->device);
    }

    /**
     * Adresse des Fotos, oder null.
     *
     * The order is the order of precision: the photo of the catalog item
     * (passive mount), otherwise that of the device model, otherwise none -
     * then the front view draws it.
     */
    public function imageUrl(): ?string
    {
        return $this->catalogItem?->imageUrl() ?? $this->deviceModel()?->imageUrl();
    }

    /** Schluessel einer eigenen Zeichnung des Geraetemodells, oder null. */
    public function zeichnung(): ?string
    {
        return $this->deviceModel()?->drawing;
    }

    /** The same as a file path - for the PDF export, which cannot do HTTP. */
    public function imagePath(): ?string
    {
        return $this->catalogItem?->imagePath() ?? $this->deviceModel()?->imagePath();
    }

    /**
     * Display name: device name from the documentation or catalog label. A device
     * whose name is optional (phone system) supplies rackName() instead.
     */
    public function label(): string
    {
        if ($this->device && method_exists($this->device, 'rackName')) {
            return $this->device->rackName();
        }

        return $this->device?->name ?? $this->name ?? '—';
    }

    protected $casts = [
        'full_depth' => 'boolean',
    ];

    /**
     * Does this item occupy the given side?
     *
     * A device at full depth blocks both sides - nothing fits behind an
     * 800 mm deep server. One at half depth blocks only its own.
     * eigene.
     */
    public function belegtSeite(string $seite): bool
    {
        return $this->side === $seite || $this->full_depth;
    }

    /** Oberste belegte Hoeheneinheit. */
    public function topUnit(): int
    {
        return $this->position + $this->height_units - 1;
    }

    /**
     * Appearance in the drawn front view. For documented devices it follows
     * from the device kind, for catalog items it sits in the column copied
     * on mounting.
     */
    public function faceAppearance(): string
    {
        if ($this->device_type) {
            foreach (config('custom.rack_device_types') as [$class, $label, $appearance]) {
                if ($class === $this->device_type) {
                    return $appearance;
                }
            }

            return 'server';
        }

        return $this->appearance ?: 'blank';
    }

    /**
     * A mounted item is named after the device mounted in it.
     */
    public function activityName(): ?string
    {
        return $this->label() ?: null;
    }
}
