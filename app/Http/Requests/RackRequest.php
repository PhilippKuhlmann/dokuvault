<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'name' => 'required|max:255',
            'height_units' => 'required|integer|between:1,60',
            'location' => 'nullable|max:255',
            'note' => 'nullable',
        ];
    }

    public function attributes(): array
    {
        return [
            'site_id' => 'Standort',
            'name' => 'Name',
            'height_units' => 'Höheneinheiten',
            'location' => 'Ort',
            'note' => 'Notiz',
        ];
    }
}
