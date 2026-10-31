<?php

namespace App\Livewire;

use App\Http\Requests\RackCatalogItemRequest;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The preview in the catalog form: the item in a small rack.
 *
 * Why a component at all? The drawing hangs on appearance AND height - a
 * patch panel of 2 U has two port rows, not a stretched one. Delivering all
 * eleven appearances in all eight heights up front and switching with x-show
 * would be 737 KB of markup on one form page. This way exactly one drawing
 * follows on every change.
 *
 * No permission check: nothing is read and nothing is written here, only an
 * image comes into being from values that are all checked against fixed
 * lists.
 */
class RackCatalogPreview extends Component
{
    public string $appearance = 'blank';

    public int $he = 1;

    /** Address of the photo already stored; without one it is drawn. */
    public ?string $bild = null;

    /** Key of an own drawing; checked against the list. */
    public ?string $drawing = null;

    public function mount(string $appearance = 'blank', int $he = 1, ?string $bild = null,
        ?string $drawing = null): void
    {
        $this->bild = $bild;
        $this->aendern($appearance, $he, $drawing);
    }

    /**
     * The form reports every change through a browser event.
     *
     * It is an ordinary form, not a Livewire form - the fields belong to the
     * submit and can therefore not hang on this component through wire:model.
     * Komponente haengen.
     */
    #[On('rack-preview')]
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
        return view('livewire.rack-catalog-preview');
    }
}
