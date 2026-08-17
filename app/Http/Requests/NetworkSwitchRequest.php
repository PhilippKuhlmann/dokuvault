<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidiertBeschaffung;
use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NetworkSwitchRequest extends FormRequest
{
    use ValidiertBeschaffung;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'name' => 'required|max:255',
            'manufacturer' => 'nullable',
            'model' => 'nullable|max:255',
            'serialNumber' => 'nullable|max:255',
            'username' => 'nullable',
            'password' => 'nullable',
            'port' => 'nullable|numeric',
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
            'username' => 'Benutzername',
            'password' => 'Passwort',
            'port' => 'Port',
            ...$this->beschaffungBezeichnungen(),
        ];
    }
}
