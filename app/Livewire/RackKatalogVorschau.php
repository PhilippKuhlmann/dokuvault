<?php

namespace App\Livewire;

use App\Http\Requests\RackCatalogItemRequest;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Die Vorschau im Katalogformular: das Element in einem kleinen Schrank.
 *
 * Warum ueberhaupt eine Komponente? Die Zeichnung haengt an Darstellung UND
 * Hoehe - ein Patchfeld mit 2 HE hat zwei Portreihen, keine gestreckte. Alle
 * elf Darstellungen in allen acht Hoehen vorab auszuliefern und per x-show
 * umzuschalten waeren 737 KB Auszeichnung auf einer Formularseite. So kommt
 * bei jeder Aenderung genau eine Zeichnung nach.
 *
 * Keine Rechtepruefung: Hier wird nichts gelesen und nichts geschrieben, es
 * entsteht nur ein Bild aus zwei Werten, die beide gegen feste Listen
 * geprueft werden.
 */
class RackKatalogVorschau extends Component
{
    public string $appearance = 'blank';

    public int $he = 1;

    /** Adresse des bereits gespeicherten Fotos; ohne eines wird gezeichnet. */
    public ?string $bild = null;

    /** Schluessel einer eigenen Zeichnung; geprueft gegen die Liste. */
    public ?string $drawing = null;

    public function mount(string $appearance = 'blank', int $he = 1, ?string $bild = null,
        ?int $ports = null, ?int $sfp = null, bool $poe = false, ?bool $display = null,
        ?string $drawing = null): void
    {
        $this->bild = $bild;
        $this->display = $display;
        $this->aendern($appearance, $he, $ports, $sfp, $poe, $display, $drawing);
    }

    /**
     * Das Formular meldet jede Aenderung per Browser-Ereignis.
     *
     * Es ist ein gewoehnliches Formular, kein Livewire-Formular - die Felder
     * gehoeren zum Absenden und koennen deshalb nicht per wire:model an dieser
     * Komponente haengen.
     */
    #[On('rack-vorschau')]
    public function aendern(?string $appearance = null, $he = null, $drawing = null): void
    {
        if ($appearance !== null && array_key_exists($appearance, config('custom.rack_appearances'))) {
            $this->appearance = $appearance;
        }

        $this->he = max(1, min((int) $he, RackCatalogItemRequest::MAX_HE));

        $this->drawing = ($drawing !== null && $drawing !== '' && array_key_exists($drawing, config('custom.rack_model_drawings')))
            ? $drawing
            : null;
    }

    public function render()
    {
        return view('livewire.rack-katalog-vorschau');
    }
}
