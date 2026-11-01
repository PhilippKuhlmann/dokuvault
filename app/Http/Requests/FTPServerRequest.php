<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FTPServerRequest extends FormRequest
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
            // Username and password do not hang on the server: a server has
            // several accounts, and they are attached through
            // mit "Logins Allgemein" verknuepft.
            'host' => 'required|max:255',
            'description' => 'max:255',
        ];
    }

    public function attributes()
    {
        return [
            'host' => 'Host',
            'description' => 'Beschreibung',
        ];
    }
}
