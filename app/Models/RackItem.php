<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein Einbau im Rack: entweder ein verknuepftes dokumentiertes Geraet
 * (device_type/device_id) oder ein passives Katalogelement (nur name).
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
     * Der Katalogeintrag, aus dem dieser Einbau stammt - nur fuer sein Bild.
     *
     * Alles Beschreibende (Bezeichnung, Darstellung, Hoehe) wurde beim Einbau
     * kopiert und bleibt auch dann stehen, wenn der Eintrag verschwindet. Beim
     * Bild geht das nicht: Die Datei haengt am Katalogeintrag. Faellt er weg,
     * faellt das Foto weg und die gezeichnete Blende tritt an seine Stelle.
     */
    public function catalogItem()
    {
        return $this->belongsTo(RackCatalogItem::class, 'rack_catalog_item_id');
    }

    /**
     * Das Geraetemodell hinter einem dokumentierten Einbau, oder null.
     *
     * Ueber Hersteller und Modell des Geraets gefunden, nicht ueber eine
     * Verweisspalte - siehe DeviceModel.
     */
    public function geraeteModell(): ?DeviceModel
    {
        return DeviceModel::fuerGeraet($this->device);
    }

    /**
     * Adresse des Fotos, oder null.
     *
     * Die Reihenfolge ist die Reihenfolge der Genauigkeit: das Foto des
     * Katalogelements (passiver Einbau), sonst das des Geraetemodells,
     * sonst keines - dann zeichnet die Frontansicht.
     */
    public function bildUrl(): ?string
    {
        return $this->catalogItem?->bildUrl() ?? $this->geraeteModell()?->bildUrl();
    }

    /** Schluessel einer eigenen Zeichnung des Geraetemodells, oder null. */
    public function zeichnung(): ?string
    {
        return $this->geraeteModell()?->drawing;
    }

    /** Dasselbe als Dateipfad - fuer den PDF-Export, der kein HTTP kann. */
    public function bildPfad(): ?string
    {
        return $this->catalogItem?->bildPfad() ?? $this->geraeteModell()?->bildPfad();
    }

    /** Anzeigename: Geraetename aus der Doku bzw. Katalogbezeichnung. */
    public function label(): string
    {
        return $this->device?->name ?? $this->name ?? '—';
    }

    protected $casts = [
        'full_depth' => 'boolean',
    ];

    /**
     * Belegt dieser Einbau die angegebene Seite?
     *
     * Ein Geraet in voller Tiefe blockiert beide Seiten - hinter einem
     * 800 mm tiefen Server passt nichts mehr. Eines in halber Tiefe nur die
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
     * Darstellung in der gezeichneten Frontansicht. Bei dokumentierten Geraeten
     * ergibt sie sich aus dem Geraetetyp, bei Katalogelementen steht sie in der
     * beim Einbau kopierten Spalte.
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
     * Ein Einbau heisst wie das Geraet, das eingebaut ist.
     */
    public function protokollName(): ?string
    {
        return $this->label() ?: null;
    }
}
