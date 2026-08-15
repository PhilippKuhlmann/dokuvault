<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DECTRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules()
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'role' => 'max:255',
            'manufacturer' => 'max:255',
            'model' => 'max:255',
            'serialNumber' => 'max:255',
            'port' => 'max:255',
            'mac' => 'max:255',
            'username' => 'max:255',
            'password' => 'max:255',
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'role' => 'Rolle',
            'manufacturer' => 'Hersteller',
            'model' => 'Model',
            'serialNumber' => 'Seriennmmer',
            'port' => 'Port',
            'mac' => 'MAC-Adresse',
            'username' => 'Benutzername',
            'password' => 'Passwort',
        ];
    }
}
