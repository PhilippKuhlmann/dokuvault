<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PatchPanelRequest extends FormRequest
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
            // 96 Ports deckt auch die groessten Felder ab; darueber wird die
            // Zeichnung ohnehin unlesbar.
            'port_count' => 'required|integer|between:1,96',
            'height_units' => 'required|integer|between:1,10',
            'manufacturer' => 'nullable|max:255',
            'model' => 'nullable|max:255',
            'note' => 'nullable',
        ];
    }

    public function attributes(): array
    {
        return [
            'site_id' => 'Standort',
            'name' => 'Name',
            'port_count' => 'Portanzahl',
            'height_units' => 'Höheneinheiten',
            'manufacturer' => 'Hersteller',
            'model' => 'Modell',
            'note' => 'Notiz',
        ];
    }
}
