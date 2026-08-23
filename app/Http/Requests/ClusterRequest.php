<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClusterRequest extends FormRequest
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
            // Rule::in gegen die Konfiguration: Sonst liesse sich ueber ein
            // gefaelschtes Formular ein beliebiger Typ hineinschreiben.
            'type' => ['nullable', Rule::in(array_keys(config('custom.cluster_types')))],
            'note' => 'nullable|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'site_id' => 'Standort',
            'name' => 'Name',
            'type' => 'Art',
            'note' => 'Notiz',
        ];
    }
}
