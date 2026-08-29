<?php

namespace App\Http\Requests;

use App\Models\RackCatalogItem;
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
            // Eindeutig, damit die Palette keine zwei gleich benannten Eintraege zeigt.
            // Beim Bearbeiten den eigenen Datensatz ausnehmen.
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rack_catalog_items', 'name')
                    ->ignore($this->route('rackcatalogitem')),
            ],
            // Acht Hoeheneinheiten sind die Obergrenze: Groesser gibt es
            // 19"-Einbauten praktisch nicht, und die Vorschau zeigt genau
            // diesen Ausschnitt. Dieselbe Grenze zieht RackEditor::setHeight.
            'height_units' => 'required|integer|min:1|max:'.self::MAX_HE,
            'full_depth' => 'required|boolean',
            'sort_order' => 'nullable|integer|min:0|max:65535',
            // Nur bekannte Darstellungen - der Wert steuert, welche Zeichnung
            // die Frontansicht rendert.
            'appearance' => ['required', Rule::in(array_keys(config('custom.rack_appearances')))],
            // Eigenes Foto der Frontblende. Ist eines hinterlegt, tritt es an
            // die Stelle der Zeichnung; die Darstellung bleibt als Rueckfall
            // stehen. SVG ist nicht dabei - siehe RackCatalogItem::FORMATE.
            'image' => ['nullable', 'image', 'mimes:'.implode(',', RackCatalogItem::FORMATE), 'max:2048'],
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
