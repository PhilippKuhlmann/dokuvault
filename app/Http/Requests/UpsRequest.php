<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;

class UpsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'name' => 'required|max:255',
            'manufacturer' => 'max:255',
            'model' => 'max:255',
            'serialNumber' => 'max:255',
            'capacity' => 'max:255',
            'runtime' => 'max:255',
            'notes' => 'nullable',
        ];
    }

    public function attributes(): array
    {
        return ['site_id' => 'Standort', 'name' => 'Name', 'manufacturer' => 'Hersteller', 'model' => 'Model', 'serialNumber' => 'Seriennummer', 'ip' => 'IP', 'capacity' => 'Kapazität', 'runtime' => 'Laufzeit', 'notes' => 'Notizen'];
    }
}
