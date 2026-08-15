<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecorderRequest extends FormRequest
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
            'name' => 'required',
            'manufacturer' => '',
            'model' => '',
            'serialNumber' => '',
            'ip' => 'nullable|ipv4',
            'port' => 'required',
            'username' => '',
            'password' => '',
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'name' => 'Name',
            'manufacturer' => 'Hersteller',
            'model' => 'Model',
            'serialNumber' => 'Seriennummer',
            'ip' => 'IP',
            'port' => 'Port',
            'username' => 'Benutzername',
            'password' => 'Passwort',
        ];
    }
}
