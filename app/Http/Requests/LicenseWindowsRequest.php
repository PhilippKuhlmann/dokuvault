<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LicenseWindowsRequest extends FormRequest
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
            // The column is NOT NULL - without a rule an empty choice ended
            // in a database error instead of a message.
            'operating_system_id' => 'required',
            'key' => 'required|max:255',
            'file_name' => 'nullable',
        ];
    }

    public function attributes()
    {
        return [
            'operating_system_id' => 'Betriebssystem',
            'key' => 'Lizenzschlüssel',
            'file_path' => 'Datei',
            'file_name' => 'Datei Name',
        ];
    }
}
