<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RackCatalogItemRequest extends FormRequest
{
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
            // 42 HE ist die groesste gaengige Schrankhoehe - mehr waere nie einbaubar.
            'height_units' => 'required|integer|min:1|max:42',
            'sort_order' => 'nullable|integer|min:0|max:65535',
            // Nur bekannte Darstellungen - der Wert steuert, welche Zeichnung
            // die Frontansicht rendert.
            'appearance' => ['required', Rule::in(array_keys(config('custom.rack_appearances')))],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Bezeichnung',
            'height_units' => 'Höheneinheiten',
            'sort_order' => 'Reihenfolge',
            'appearance' => 'Darstellung',
        ];
    }
}
