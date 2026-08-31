<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginGeneralRequest extends FormRequest
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
            'name' => 'max:255',
            'username' => 'max:255',
            'password' => 'nullable|max:255',
            'description' => 'max:255',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Name',
            'username' => 'Benutzername',
            'password' => 'Passwort',
            'description' => 'Beschreibung',
        ];
    }
}
