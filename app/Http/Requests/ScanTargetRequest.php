<?php

namespace App\Http\Requests;

use App\Models\ScanTarget;
use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScanTargetRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'nullable|max:255',
            'kind' => ['required', Rule::in(array_keys(ScanTarget::ARTEN))],
            'target' => 'required|max:255',
            'login_general_id' => ['nullable', new BelongsToCustomer('login_generals')],
            'description' => 'nullable|max:2000',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Bezeichnung',
            'kind' => 'Art',
            'target' => 'Ziel',
            'login_general_id' => 'Zugangsdaten',
            'description' => 'Beschreibung',
        ];
    }
}
