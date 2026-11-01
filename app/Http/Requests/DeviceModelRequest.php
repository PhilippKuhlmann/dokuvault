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
            // Known device kinds only: the value decides which devices
            // diesen Eintrag ueberhaupt finden.
            'device_type' => ['required', Rule::in(array_keys(config('custom.rack_device_types')))],
            'manufacturer' => 'required|string|max:255',
            'model' => 'nullable|string|max:255',
            // Dieselbe Grenze wie im Rack-Katalog.
            'height_units' => 'required|integer|min:1|max:'.RackCatalogItemRequest::MAX_HE,
            'full_depth' => 'required|boolean',
            // A drawing of its own, if there is one for the model. Known keys
            // only: the value decides which view is rendered.
            //
            'drawing' => ['nullable', Rule::in(array_keys(config('custom.rack_model_drawings')))],
            'image' => ['nullable', 'image', 'mimes:'.implode(',', config('custom.bild_formate')), 'max:2048'],
            'image_remove' => 'nullable|boolean',
        ];
    }

    /**
     * A model may exist only once - compared through the same
     * normalisierten Schluessel wie beim Nachschlagen, sonst legte man neben
     * "APC" would take a second "apc " that would never be found.
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
