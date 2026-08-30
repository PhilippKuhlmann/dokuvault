<?php

namespace App\Http\Requests;

use App\Models\DeviceModel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceModelRequest extends FormRequest
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
            // Nur bekannte Geraetetypen: Der Wert entscheidet, welche Geraete
            // diesen Eintrag ueberhaupt finden.
            'device_type' => ['required', Rule::in(array_keys(config('custom.rack_device_types')))],
            'manufacturer' => 'required|string|max:255',
            'model' => 'nullable|string|max:255',
            // Dieselbe Grenze wie im Rack-Katalog.
            'height_units' => 'required|integer|min:1|max:'.RackCatalogItemRequest::MAX_HE,
            'full_depth' => 'required|boolean',
            // Eine eigene Zeichnung, falls es fuer das Modell eine gibt. Nur
            // bekannte Schluessel: Der Wert entscheidet, welche Ansicht
            // gerendert wird.
            'drawing' => ['nullable', Rule::in(array_keys(config('custom.rack_model_drawings')))],
            'image' => ['nullable', 'image', 'mimes:'.implode(',', config('custom.bild_formate')), 'max:2048'],
            'image_remove' => 'nullable|boolean',
        ];
    }

    /**
     * Ein Modell darf es nur einmal geben - verglichen wird ueber dieselben
     * normalisierten Schluessel wie beim Nachschlagen, sonst legte man neben
     * "APC" ein zweites "apc " an, das nie gefunden wuerde.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $vorhanden = DeviceModel::where('device_type', $this->input('device_type'))
                ->where('manufacturer_key', DeviceModel::schluessel($this->input('manufacturer')))
                ->where('model_key', DeviceModel::schluessel($this->input('model')))
                ->when($this->route('devicemodel'), fn ($q, $eigenes) => $q->whereKeyNot($eigenes->id ?? $eigenes))
                ->exists();

            if ($vorhanden) {
                $validator->errors()->add('manufacturer', __('Dieses Modell gibt es für den Gerätetyp schon.'));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'device_type' => 'Gerätetyp',
            'manufacturer' => 'Hersteller',
            'model' => 'Modell',
            'height_units' => 'Höheneinheiten',
            'full_depth' => 'Einbautiefe',
            'image' => 'Bild',
        ];
    }
}
