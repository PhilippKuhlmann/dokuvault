<?php

namespace App\Models;

use App\Models\Concerns\HatBild;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Ein Geraetemodell - "APC Smart-UPS 1500" - mit Foto der Frontblende.
 *
 * Kundenuebergreifend: Wer das Bild bei einem Kunden hinterlegt, hinterlegt
 * es fuer alle. Ein Foto beschreibt das Modell, nicht das Exemplar.
 *
 * Gefunden wird ein Eintrag ueber Geraetetyp, Hersteller und Modell - die
 * Felder, die an jedem Geraet ohnehin stehen. Es gibt keine Verweisspalte und
 * keine zusaetzliche Auswahl im Geraeteformular: Wer "APC" und
 * "Smart-UPS 1500" eintippt, hat das Bild damit schon.
 */
class DeviceModel extends Model
{
    use HasFactory;
    use HatBild;
    use TracksChanges;

    /** Ordner auf der local-Disk und Route, die das Bild ausliefert - siehe HatBild. */
    public const BILDORDNER = 'device-models';

    public const BILDROUTE = 'devicemodel.image';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'full_depth' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Die Vergleichsschluessel nie von aussen setzen lassen: Sie muessen
        // zu Hersteller und Modell passen, sonst findet der Abgleich nichts.
        static::saving(function (self $modell) {
            $modell->manufacturer_key = self::schluessel($modell->manufacturer);
            $modell->model_key = self::schluessel($modell->model);
        });

        // Die geladene Liste ist nach jeder Aenderung veraltet.
        static::saved(fn () => app()->forgetInstance(self::SPEICHER));
        static::deleted(fn () => app()->forgetInstance(self::SPEICHER));
    }

    /**
     * Vergleichsform eines Namens: kleingeschrieben, getrimmt,
     * Mehrfachleerzeichen zusammengezogen.
     *
     * Ohne das waeren "APC ", "apc" und "APC  Corp" drei Hersteller. Mehr
     * Normalisierung waere Raten: "Hewlett Packard" und "HP" zusammenzufuehren
     * ist eine Entscheidung, die ein Mensch trifft, nicht eine Zeichenkette.
     */
    public static function schluessel(?string $wert): string
    {
        return preg_replace('/\s+/u', ' ', trim(mb_strtolower((string) $wert)));
    }

    /**
     * Das Modell zu einem Geraet, oder null.
     *
     * Alle Eintraege werden einmal je Anfrage geladen und im Speicher
     * durchsucht: Eine Rack-Ansicht mit zwanzig Einbauten braeuchte sonst
     * zwanzig Abfragen, und die Tabelle ist klein genug, dass sich das nicht
     * lohnt.
     */
    public static function fuer(string $geraetetyp, ?string $hersteller, ?string $modell = null): ?self
    {
        if (self::schluessel($hersteller) === '') {
            return null;
        }

        return self::alle()->get(self::kennung($geraetetyp, $hersteller, $modell));
    }

    /** Der Eintrag zu einem dokumentierten Geraet, ueber seinen Typ aufgeloest. */
    public static function fuerGeraet(?Model $geraet): ?self
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

    /** Schluessel, unter dem die geladene Liste im Container liegt. */
    private const SPEICHER = 'device_models.alle';

    /**
     * Alle Eintraege, nach Kennung ansprechbar - einmal je Anfrage geladen.
     *
     * Abgelegt im Container, nicht in einer statischen Eigenschaft: Der
     * Container entsteht je Anfrage und je Test neu. Eine statische Liste
     * ueberlebte den Rollback zwischen zwei Tests und lieferte dem zweiten
     * die Eintraege des ersten.
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
     * Zeichnung des Geraetetyps - der Rueckfall, solange kein Foto hinterlegt
     * ist. Sie steht in derselben Liste, aus der auch die Rack-Ansicht sie
     * holt (config('custom.rack_device_types')).
     */
    public function getAppearanceAttribute(): string
    {
        return config('custom.rack_device_types')[$this->device_type][2] ?? 'server';
    }

    /** Beschriftung des Geraetetyps, wie sie in der Rack-Palette steht. */
    public function typBezeichnung(): string
    {
        return config('custom.rack_device_types')[$this->device_type][1] ?? $this->device_type;
    }

    /** Reihenfolge in der Pflegeliste. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('manufacturer')->orderBy('model');
    }

    /** Wie der Eintrag heisst, wenn man ihn benennen muss. */
    public function bezeichnung(): string
    {
        return trim($this->manufacturer.' '.$this->model);
    }

    public function protokollName(): ?string
    {
        return $this->bezeichnung() ?: null;
    }

    /** Adresse des Bildes, oder null. Global, wie der Eintrag selbst. */
    public function bildUrl(): ?string
    {
        return $this->image_path ? route('devicemodel.image', $this) : null;
    }

    /** Absoluter Pfad der Bilddatei - fuer den PDF-Export, der kein HTTP kann. */
    public function bildPfad(): ?string
    {
        if (! $this->image_path || ! Storage::disk('local')->exists($this->image_path)) {
            return null;
        }

        return Storage::disk('local')->path($this->image_path);
    }

    public function bildLoeschen(): void
    {
        if ($this->image_path && Storage::disk('local')->exists($this->image_path)) {
            Storage::disk('local')->delete($this->image_path);
        }
    }
}
