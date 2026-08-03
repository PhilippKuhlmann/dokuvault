<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ContactPersonRequest extends FormRequest
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
            'first_name' => 'required',
            'last_name' => 'required',
            'phone' => '',
            'mail' => '',
        ];
    }

    public function attributes()
    {
        return [
            'first_name' => 'Vorname',
            'last_name' => 'Nachname',
            'phone' => 'Telefonnummer',
            'mail' => 'E-Mail',
        ];
    }
}
