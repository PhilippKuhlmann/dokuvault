<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidiertBeschaffung;
use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OtherClientRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'name' => 'required|max:255',
            'manufacturer' => 'max:255',
            'model' => 'max:255',
            'serialNumber' => 'max:255',
            'port' => 'max:255',
            'username' => 'nullable',
            'password' => 'nullable|max:255',
            ...$this->beschaffungRegeln(),
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'name' => 'Name',
            'manufavtuter' => 'Hersteller',
            'model' => 'Model',
            'serialNumber' => 'Seriennummer',
            'port' => 'Port',
            'username' => 'Benutzer',
            'password' => 'Passwort',
            ...$this->beschaffungBezeichnungen(),
        ];
    }
}
