<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SecurepointUMARequest extends FormRequest
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
            'name' => 'required|max:255',
            'manufacturer' => 'nullable|max:255',
            'type' => 'nullable|max:255',
            'username' => 'required|max:255',
            'password' => 'nullable|max:255',
            'encryptionkey' => 'required|max:255',
            'urlAdmin' => 'required|url|max:255',
            'urlUser' => 'nullable|url|max:255',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Name',
            'manufacturer' => 'Hersteller / Produkt',
            'type' => 'Typ',
            'username' => 'Benutzername',
            'password' => 'Passwort',
            'encryptionkey' => 'Verschlüsselungscode',
            'urlAdmin' => 'Admin URL',
            'urlUser' => 'User URL',
        ];
    }
}
