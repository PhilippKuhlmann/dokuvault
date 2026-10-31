<?php

namespace App\Models;

use App\Models\Concerns\HasImage;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A device model - "APC Smart-UPS 1500" - with a photo of the front panel.
 *
 * Across customers: whoever stores the image at one customer stores it for
 * all. A photo describes the model, not the individual unit.
 *
 * An entry is found through device kind, manufacturer and model - the fields
 * that stand on every device anyway. There is no reference column and no
 * extra select in the device form: whoever types "APC" and
 * "Smart-UPS 1500" has the image already.
 */
class DeviceModel extends Model
{
    use HasFactory;
    use HasImage;
    use TracksChanges;

    /** Folder on the local disk and route that serves the image - see HasImage. */
    public const BILDORDNER = 'device-models';

    public const BILDROUTE = 'devicemodel.image';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'full_depth' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Never let the comparison keys be set from outside: they have to
        // match manufacturer and model, or the lookup finds nothing.
        static::saving(function (self $modell) {
            $modell->manufacturer_key = self::schluessel($modell->manufacturer);
            $modell->model_key = self::schluessel($modell->model);
        });

        // The loaded list is out of date after every change.
        static::saved(fn () => app()->forgetInstance(self::SPEICHER));
        static::deleted(fn () => app()->forgetInstance(self::SPEICHER));
    }

    /**
     * Vergleichsform eines Namens: kleingeschrieben, getrimmt,
     * Mehrfachleerzeichen zusammengezogen.
     *
     * Without it, "APC ", "apc" and "APC  Corp" would be three manufacturers.
     * More normalisation would be guessing: merging "Hewlett Packard" and
     * "HP" is a decision a person makes, not a string.
     */
    public static function schluessel(?string $wert): string
    {
        return preg_replace('/\s+/u', ' ', trim(mb_strtolower((string) $wert)));
    }

    /**
     * The model for a device, or null.
     *
     * All entries are loaded once per request and searched in memory: a rack
     * view with twenty mounted items would otherwise need twenty queries, and
     * the table is small enough that it is not worth it.
     */
    public static function fuer(string $geraetetyp, ?string $hersteller, ?string $modell = null): ?self
    {
        if (self::schluessel($hersteller) === '') {
            return null;
        }

        return self::alle()->get(self::kennung($geraetetyp, $hersteller, $modell));
    }

    /** The entry for a documented device, resolved through its kind. */
    public static function forDevice(?Model $geraet): ?self
    {
        if (! $geraet) {
            return null;
        }

        foreach (config('custom.rack_device_types') as $key => [$class]) {
            if ($geraet instanceof $class) {
                return self::fuer($key, $geraet->manufacturer ?? null, $geraet->model ?? null);
            }
        }

        return null;
    }

    /** Key the loaded list sits under in the container. */
    private const SPEICHER = 'device_models.alle';

    /**
     * All entries, addressable by identifier - loaded once per request.
     *
     * Kept in the container, not in a static property: the container comes
     * into being per request and per test. A static list outlived the rollback
     * between two tests and handed the second one the entries of the first.
     */
    public static function alle()
    {
        if (! app()->bound(self::SPEICHER)) {
            app()->instance(self::SPEICHER, self::all()->keyBy(
                fn (self $m) => self::kennung($m->device_type, $m->manufacturer, $m->model)
            ));
        }

        return app(self::SPEICHER);
    }

    private static function kennung(string $geraetetyp, ?string $hersteller, ?string $modell): string
    {
        return $geraetetyp.'|'.self::schluessel($hersteller).'|'.self::schluessel($modell);
    }

    /**
     * The drawing of the device kind - the fallback as long as no photo is
     * stored. It sits in the same list the rack view takes it from
     * (config('custom.rack_device_types')).
     */
    public function getAppearanceAttribute(): string
    {
        return config('custom.rack_device_types')[$this->device_type][2] ?? 'server';
    }

    /** Label of the device kind, as it stands in the rack palette. */
    public function typeLabel(): string
    {
        return config('custom.rack_device_types')[$this->device_type][1] ?? $this->device_type;
    }

    /** Order in the maintenance list. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('manufacturer')->orderBy('model');
    }

    /** What the entry is called when it has to be named. */
    public function bezeichnung(): string
    {
        return trim($this->manufacturer.' '.$this->model);
    }

    public function activityName(): ?string
    {
        return $this->bezeichnung() ?: null;
    }

    /** Address of the image, or null. Global, like the entry itself. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? route('devicemodel.image', $this) : null;
    }

    /** Absolute path of the image file - for the PDF export, which cannot do HTTP. */
    public function imagePath(): ?string
    {
        if (! $this->image_path || ! Storage::disk('local')->exists($this->image_path)) {
            return null;
        }

        return Storage::disk('local')->path($this->image_path);
    }

    public function deleteImage(): void
    {
        if ($this->image_path && Storage::disk('local')->exists($this->image_path)) {
            Storage::disk('local')->delete($this->image_path);
        }
    }
}
