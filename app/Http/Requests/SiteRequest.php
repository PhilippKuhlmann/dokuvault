<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SiteRequest extends FormRequest
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
            'name' => 'required',
            'street' => '',
            'house_number' => '',
            'zip' => '',
            'city' => '',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Name',
            'street' => 'Straße',
            'house_number' => 'Hausnummer',
            'zip' => 'PLZ',
            'city' => 'Stadt',
        ];
    }
}
