<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesProcurement;
use App\Models\Setting;
use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServerRequest extends FormRequest
{
    use ValidatesProcurement;

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
    /** A tower server has no mounting depth - the value stays at the default. */
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
            // Nullable: the vast majority of servers stand alone.
            'cluster_id' => ['nullable', new BelongsToCustomer('clusters')],
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
            ...$this->procurementRules(),
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'cluster_id' => 'Cluster',
            'name' => 'Name',
            'type' => 'Typ',
            'manufacturer' => 'Hersteller',
            'model' => 'Modell',
            'serialNumber' => 'Seriennummer',
            'bmcIp' => 'BMC IP',
            'bmcUser' => 'BMC Benutzer',
            'bmcPassword' => 'BMC Passwort',
            'services' => 'Dienste',
            'operating_system_id' => 'Betriebssystem',
            'remoteID' => Setting::remoteAccess()['id_label'],
            'remotePassword' => Setting::remoteAccess()['password_label'],
            ...$this->procurementLabels(),
        ];
    }
}
