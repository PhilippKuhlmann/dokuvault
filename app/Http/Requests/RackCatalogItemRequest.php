<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RackCatalogItemRequest extends FormRequest
{
    /** Groesster zulaessiger Einbau in Hoeheneinheiten. */
    public const MAX_HE = 8;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            // Unique, so the palette shows no two entries of the same name.
            // Beim Bearbeiten den eigenen Datensatz ausnehmen.
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rack_catalog_items', 'name')
                    ->ignore($this->route('rackcatalogitem')),
            ],
            // Eight height units are the cap: 19-inch items practically do not
            // come larger, and the preview shows exactly
            // diesen Ausschnitt. Dieselbe Grenze zieht RackEditor::setHeight.
            'height_units' => 'required|integer|min:1|max:'.self::MAX_HE,
            'full_depth' => 'required|boolean',
            'sort_order' => 'nullable|integer|min:0|max:65535',
            // Known appearances only - the value steers which drawing the
            // front view renders.
            'appearance' => ['required', Rule::in(array_keys(config('custom.rack_appearances')))],
            // An own photo of the front panel. If one is stored it takes the
            // place of the drawing; the appearance stays as a fallback. SVG is
            // not among them - see config/custom.php.
            'image' => ['nullable', 'image', 'mimes:'.implode(',', config('custom.bild_formate')), 'max:2048'],
            'image_remove' => 'nullable|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Bezeichnung',
            'height_units' => 'Höheneinheiten',
            'full_depth' => 'Einbautiefe',
            'sort_order' => 'Reihenfolge',
            'appearance' => 'Darstellung',
            'image' => 'Bild',
        ];
    }
}
