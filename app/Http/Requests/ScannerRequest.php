<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesProcurement;
use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;

class ScannerRequest extends FormRequest
{
    use ValidatesProcurement;

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'name' => 'required|max:255',
            'manufacturer' => 'max:255',
            'model' => 'max:255',
            'serialNumber' => 'max:255',
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
            ...$this->procurementLabels(),
        ];
    }
}
