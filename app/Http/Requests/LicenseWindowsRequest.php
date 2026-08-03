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
            'operating_system_id' => '',
            'key' => 'required|max:255',
            'file_path' => 'nullable',
            'file_name' => 'nullable',
        ];
    }

    public function attributes()
    {
        return [
            'operating_system_id' => 'Betriebsystem',
            'key' => 'Key',
            'file_path' => 'Datei',
            'file_name' => 'Datei Name',
        ];
    }
}
