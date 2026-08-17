<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidiertBeschaffung;
use App\Models\Setting;
use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServerRequest extends FormRequest
{
    use ValidiertBeschaffung;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    /** Ein Standserver hat keine Einbautiefe - der Wert bleibt auf dem Standard. */
    protected function prepareForValidation(): void
    {
        if ($this->input('form_factor') !== 'rack') {
            $this->merge(['full_depth' => true, 'height_units' => 1]);
        }
    }

    public function rules()
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'name' => 'required|max:255',
            'type' => 'max:255',
            'manufacturer' => 'max:255',
            'model' => 'max:255',
            'form_factor' => ['required', Rule::in(array_keys(config('custom.server_form_factors')))],
            'full_depth' => 'required_if:form_factor,rack|boolean',
            'height_units' => 'required_if:form_factor,rack|integer|min:1|max:20',
            'serialNumber' => 'max:255',
            'bmcIp' => 'max:255',
            'bmcUser' => 'max:255',
            'bmcPassword' => 'max:255',
            'services' => 'max:255',
            'operating_system_id' => 'required',
            'remoteID' => '',
            'remotePassword' => '',
            ...$this->beschaffungRegeln(),
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'name' => 'Name',
            'type' => 'Typ',
            'manufacturer' => 'Hersteller',
            'model' => 'Model',
            'serialNumber' => 'Seriennummer',
            'bmcIp' => 'BMC IP',
            'bmcUser' => 'BMC Benutzer',
            'bmcPassword' => 'BMC Passwort',
            'services' => 'Dienste',
            'operating_system_id' => 'Betriebssystem',
            'remoteID' => Setting::fernwartung()['id_label'],
            'remotePassword' => Setting::fernwartung()['password_label'],
            ...$this->beschaffungBezeichnungen(),
        ];
    }
}
