<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NASRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'name' => 'max:255',
            'manufacturer' => 'max:255',
            'model' => 'max:255',
            'serialNumber' => 'max:255',
            'ip1' => 'nullable|max:255',
            'ip2' => 'max:255',
            'port' => 'numeric',
            'username' => 'required|max:255',
            'password' => 'required|max:255',
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
            'ip1' => 'IP 1',
            'ip2' => 'IP 2',
            'port' => 'Port',
            'username' => 'Benutzername',
            'password' => 'Passwort',
        ];
    }
}
