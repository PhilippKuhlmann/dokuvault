<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesProcurement;
use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NASRequest extends FormRequest
{
    use ValidatesProcurement;

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
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'name' => 'max:255',
            'manufacturer' => 'max:255',
            'model' => 'max:255',
            'serialNumber' => 'max:255',
            'firmware' => 'nullable|max:255',
            'port' => 'numeric',
            ...$this->procurementRules(),
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'name' => 'Name',
            'manufacturer' => 'Hersteller',
            'model' => 'Modell',
            'serialNumber' => 'Seriennummer',
            'firmware' => 'Firmware',
            'port' => 'Port',
            ...$this->procurementLabels(),
        ];
    }
}
