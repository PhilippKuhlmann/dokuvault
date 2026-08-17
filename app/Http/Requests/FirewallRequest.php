<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidiertBeschaffung;
use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FirewallRequest extends FormRequest
{
    use ValidiertBeschaffung;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules()
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'name' => 'required|max:255',
            'manufacturer' => 'nullable|max:255',
            'model' => 'nullable|max:255',
            'serialNumber' => 'nullable|max:255',
            'firmware' => 'nullable|max:255',
            'management_url' => 'nullable|max:255',
            // Anders als beim Router nicht verpflichtend: Das Kennwort kann im
            // Passwort-Manager liegen und hier nur verlinkt sein.
            'username' => 'nullable|max:255',
            'password' => 'nullable|max:255',
            'port' => 'nullable|numeric',
            'subscription_until' => 'nullable|date',
            'height_units' => 'nullable|integer|min:1|max:48',
            'full_depth' => 'nullable|boolean',
            'notes' => 'nullable',
            ...$this->beschaffungRegeln(),
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'name' => 'Name',
            'manufacturer' => 'Hersteller',
            'model' => 'Modell',
            'serialNumber' => 'Seriennummer',
            'firmware' => 'Firmware',
            'management_url' => 'Verwaltungsoberfläche',
            'username' => 'Benutzername',
            'password' => 'Passwort',
            'port' => 'Port',
            'subscription_until' => 'Subscription bis',
            'height_units' => 'Höheneinheiten',
            'full_depth' => 'Volle Tiefe',
            'notes' => 'Notizen',
            ...$this->beschaffungBezeichnungen(),
        ];
    }
}
