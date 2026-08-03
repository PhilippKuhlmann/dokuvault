<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginNASRequest extends FormRequest
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
            'nas_id' => ['required', new BelongsToCustomer('nas')],
            'username' => 'required|max:255',
            'password' => 'required|max:255',
            'description' => 'max:255',
        ];
    }

    public function attributes()
    {
        return [
            'nas_id' => 'NAS',
            'username' => 'Benutzername',
            'password' => 'Passwort',
            'description' => 'Beschreibung',
        ];
    }
}
