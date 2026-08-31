<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Ein Model mit einem hinterlegten Bild.
 *
 * Der Rack-Katalog und die Geraetemodelle fuehren beide eines, und beide
 * behandeln es gleich: Die Datei liegt privat auf der local-Disk und geht
 * durch einen Controller heraus - wie jede Datei dieser App. Ein Symlink nach
 * public waere der einzige Sonderweg und muesste auf jedem Server
 * eingerichtet werden.
 *
 * Das Model bringt zwei Angaben mit:
 *
 *   BILDORDNER    Ordner auf der local-Disk
 *   BILDROUTE     Name der Route, die das Bild ausliefert
 */
trait HatBild
{
    /**
     * Sonst bliebe zu jedem geloeschten Eintrag eine Datei liegen, die
     * niemand mehr findet.
     */
    public static function bootHatBild(): void
    {
        static::deleting(fn ($eintrag) => $eintrag->bildLoeschen());
    }

    /** Adresse des Bildes, oder null. */
    public function bildUrl(): ?string
    {
        return $this->image_path ? route(static::BILDROUTE, $this) : null;
    }

    /** Absoluter Pfad der Bilddatei - fuer den PDF-Export, der kein HTTP kann. */
    public function bildPfad(): ?string
    {
        return $this->hatBilddatei() ? Storage::disk('local')->path($this->image_path) : null;
    }

    /** Die abgelegte Datei entfernen. Die Spalte bleibt unberuehrt. */
    public function bildLoeschen(): void
    {
        if ($this->hatBilddatei()) {
            Storage::disk('local')->delete($this->image_path);
        }
    }

    /** Liegt zur eingetragenen Adresse auch wirklich eine Datei? */
    private function hatBilddatei(): bool
    {
        return (bool) $this->image_path && Storage::disk('local')->exists($this->image_path);
    }
}
