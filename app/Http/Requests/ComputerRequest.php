<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesProcurement;
use App\Models\Setting;
use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;

class ComputerRequest extends FormRequest
{
    use ValidatesProcurement;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'name' => 'required|max:255',
            'manufacturer' => 'max:255',
            'model' => 'max:255',
            'serialNumber' => 'max:255',
            'operating_system_id' => 'required',
            'remoteID' => '',
            'remotePassword' => '',
            ...$this->procurementRules(),
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'name' => 'Name',
            'manufavtuter' => 'Hersteller',
            'model' => 'Modell',
            'serialNumber' => 'Seriennummer',
            'remoteID' => Setting::remoteAccess()['id_label'],
            'remotePassword' => Setting::remoteAccess()['password_label'],
            ...$this->procurementLabels(),
        ];
    }
}
