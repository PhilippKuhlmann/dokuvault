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
            'auto_check' => 'nullable|boolean',
            'dkim_selectors' => ['nullable', 'max:255', 'regex:/^[A-Za-z0-9._\-,\s]*$/'],
            'notes' => 'nullable',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Domain', 'registrar' => 'Registrar', 'expiry_date' => 'Ablaufdatum', 'nameserver1' => 'Nameserver 1', 'nameserver2' => 'Nameserver 2', 'auto_check' => 'Automatisch prüfen', 'dkim_selectors' => 'Eigene DKIM-Selektoren', 'notes' => 'Notizen'];
    }
}
