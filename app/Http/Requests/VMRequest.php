<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;

class VMRequest extends FormRequest
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
            'server_id' => ['nullable', new BelongsToCustomer('servers')],
            'name' => 'required|max:255',
            'services' => 'max:255',
            // Die Spalte ist NOT NULL - ohne Regel endete eine leere
            // Auswahl in einem Datenbankfehler statt in einer Meldung.
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
            'services' => 'Dienste',
            'cidr' => 'CIDR',
            'operating_system_id' => 'Betriebssystem',
            'remoteID' => Setting::fernwartung()['id_label'],
            'remotePassword' => Setting::fernwartung()['password_label'],
        ];
    }
}
