<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Passives Rack-Element (Patchfeld, Blindplatte, Fachboden ...), das im
 * Adminbereich gepflegt wird und in jedem Rack einbaubar ist.
 *
 * Beim Einbau wird die Bezeichnung nach rack_items kopiert - ein spaeter
 * geaenderter oder geloeschter Katalogeintrag veraendert also keine
 * bestehende Rack-Dokumentation.
 */
class RackCatalogItem extends Model
{
    use HasFactory;
    use TracksChanges;

    /** Ordner auf der local-Disk, in dem die hochgeladenen Bilder liegen. */
    public const BILDORDNER = 'rack-catalog';

    /**
     * Erlaubte Bildformate.
     *
     * Ohne SVG, aus demselben Grund wie bei den Logos: Eine SVG-Datei darf
     * Skript enthalten, und von derselben Herkunft ausgeliefert waere das
     * ausfuehrbarer Code in einer Dokumentation, in der Kennwoerter stehen.
     */
    public const FORMATE = ['png', 'jpg', 'jpeg', 'webp'];

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'full_depth' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Sonst bliebe zu jedem geloeschten Eintrag eine Datei liegen, die
        // niemand mehr findet.
        static::deleting(fn (self $eintrag) => $eintrag->bildLoeschen());
    }

    /** Reihenfolge in der Palette des Rack-Editors. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Adresse des Bildes, oder null.
     *
     * Die Datei liegt privat und geht durch einen Controller heraus - wie
     * jede Datei dieser App. Ein Symlink nach public waere der einzige
     * Sonderweg und muesste auf jedem Server eingerichtet werden.
     */
    public function bildUrl(): ?string
    {
        return $this->image_path ? route('rackcatalogitem.image', $this) : null;
    }

    /** Absoluter Pfad der Bilddatei - fuer den PDF-Export, der kein HTTP kann. */
    public function bildPfad(): ?string
    {
        if (! $this->image_path || ! Storage::disk('local')->exists($this->image_path)) {
            return null;
        }

        return Storage::disk('local')->path($this->image_path);
    }

    /** Die abgelegte Datei entfernen. Die Spalte bleibt unberuehrt. */
    public function bildLoeschen(): void
    {
        if ($this->image_path && Storage::disk('local')->exists($this->image_path)) {
            Storage::disk('local')->delete($this->image_path);
        }
    }
}
