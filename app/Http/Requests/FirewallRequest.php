<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidiertBeschaffung;
use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'form_factor' => ['nullable', Rule::in(array_keys(config('custom.firewall_form_factors')))],
            'url_user' => 'nullable|max:255',
            'url_external' => 'nullable|max:255',
            'usc_pin' => 'nullable|max:255',
            'cloud_backup_password' => 'nullable|max:255',
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
            'form_factor' => 'Bauform',
            'url_user' => 'Benutzerportal',
            'url_external' => 'Externer Zugang',
            'usc_pin' => 'USC-PIN',
            'cloud_backup_password' => 'Cloud-Backup-Kennwort',
            'subscription_until' => 'Subscription bis',
            'height_units' => 'Höheneinheiten',
            'full_depth' => 'Volle Tiefe',
            'notes' => 'Notizen',
            ...$this->beschaffungBezeichnungen(),
        ];
    }
}
