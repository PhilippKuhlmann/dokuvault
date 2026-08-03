<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|max:255',
            'registrar' => 'max:255',
            'expiry_date' => 'nullable|date',
            'nameserver1' => 'max:255',
            'nameserver2' => 'max:255',
            'notes' => 'nullable',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Domain', 'registrar' => 'Registrar', 'expiry_date' => 'Ablaufdatum', 'nameserver1' => 'Nameserver 1', 'nameserver2' => 'Nameserver 2', 'notes' => 'Notizen'];
    }
}
