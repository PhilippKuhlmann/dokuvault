<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesProcurement;
use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecorderRequest extends FormRequest
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
    public function rules()
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'name' => 'required',
            'manufacturer' => '',
            'model' => '',
            'serialNumber' => '',
            'port' => 'required',
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
            'port' => 'Port',
            ...$this->procurementLabels(),
        ];
    }
}
