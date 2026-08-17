<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidiertBeschaffung;
use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;

class SecurepointUTMRequest extends FormRequest
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
            'name' => 'required|max:255',
            'type' => 'nullable|max:255',
            'serialNumber' => 'nullable|max:255',
            'username' => 'required|max:255',
            'password' => 'required|max:255',
            'cloudBackupPassword' => 'required|max:255',
            // Ohne Regel faellt das Feld aus validated() heraus, und der
            // Controller speichert nur Validiertes: Die eingegebene PIN
            // verschwand kommentarlos, obwohl Formular und Anzeige sie fuehren.
            'uscpin' => 'nullable|max:255',
            'urlAdmin' => 'required|url|max:255',
            'urlUser' => 'nullable|url|max:255',
            'urlExternal' => 'nullable|url|max:255',
            ...$this->beschaffungRegeln(),
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'name' => 'Name',
            'type' => 'Typ',
            'serialNumber' => 'Seriennummer',
            'username' => 'Benutzername',
            'password' => 'Passwort',
            'cloudBackupPassword' => 'Cloud Backup Passwort',
            'uscpin' => 'USC-PIN',
            'urlAdmin' => 'Admin URL',
            'urlUser' => 'User URL',
            'utlExternal' => 'Externe URL',
            ...$this->beschaffungBezeichnungen(),
        ];
    }
}
