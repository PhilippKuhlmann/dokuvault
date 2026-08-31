<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidiertBeschaffung;
use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;

class PhoneSystemRequest extends FormRequest
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
    public function rules()
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'manufacturer' => 'max:255',
            'type' => 'max:255',
            'model' => 'max:255',
            'serialNumber' => 'max:255',
            'port' => 'max:255',
            'username' => 'max:255',
            'password' => 'nullable|max:255',
            ...$this->beschaffungRegeln(),
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'manufacturer' => 'Hersteller',
            'type' => 'Typ',
            'model' => 'Model',
            'serialNumber' => 'Seriennummer',
            'port' => 'Port',
            'username' => 'Benutzername',
            'password' => 'Passwort',
            ...$this->beschaffungBezeichnungen(),
        ];
    }
}
