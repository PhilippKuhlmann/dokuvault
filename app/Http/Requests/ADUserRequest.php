<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ADUserRequest extends FormRequest
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
            'firstName' => 'max:255',
            'lastName' => 'max:255',
            'username' => 'required|max:255',
            // Nullable: per Agent importierte AD-Benutzer haben kein Passwort hinterlegt.
            'password' => 'nullable|max:255',
            'email' => 'nullable|email|max:255',
            'enabled' => 'nullable|boolean',
            'hidden' => '',
        ];
    }

    public function attributes()
    {
        return [
            'firstName' => 'Vorname',
            'lastName' => 'Nachname',
            'username' => 'Benutzername',
            'password' => 'Passwort',
            'email' => 'E-Mail',
            'enabled' => 'Status',
        ];
    }
}
