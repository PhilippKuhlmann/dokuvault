<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LicenseSoftwareRequest extends FormRequest
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
            'name' => 'required|max:255',
            'key' => 'nullable',
            'username' => 'nullable',
            'password' => 'nullable',
            'start_date' => 'nullable',
            'end_date' => 'nullable',
            'abo' => 'nullable',
            'file_path' => 'nullable',
            'file_name' => 'nullable',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Name',
            'key' => 'Key',
            'username' => 'Benutzername',
            'password' => 'Passwort',
            'start_data' => 'Start Datum',
            'end_date' => 'End Datum',
            'abo' => 'Abomemount',
            'file_path' => 'Datei',
            'file_name' => 'Datei Name',
        ];
    }
}
