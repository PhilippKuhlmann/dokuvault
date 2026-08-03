<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;

class ServerRequest extends FormRequest
{
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
    public function rules()
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'name' => 'required|max:255',
            'type' => 'max:255',
            'manufacturer' => 'max:255',
            'model' => 'max:255',
            'serialNumber' => 'max:255',
            'ip1' => 'max:255',
            'ip2' => 'max:255',
            'bmcIp' => 'max:255',
            'bmcUser' => 'max:255',
            'bmcPassword' => 'max:255',
            'services' => 'max:255',
            'operating_system_id' => 'required',
            'remoteID' => '',
            'remotePassword' => '',
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
            'ip1' => 'IP 1',
            'ip2' => 'IP 2',
            'bmcIp' => 'BMC IP',
            'bmcUser' => 'BMC Benutzer',
            'bmcPassword' => 'BMC Passwort',
            'services' => 'Dienste',
            'operating_system_id' => 'Betriebsystem',
            'remoteID' => 'Rustdesk ID',
            'remotePassword' => 'Rustdesk Passwort',
        ];
    }
}
