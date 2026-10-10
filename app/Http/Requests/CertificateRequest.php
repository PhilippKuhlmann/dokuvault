<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|max:255',
            'common_name' => 'nullable|max:255',
            'issuer' => 'nullable|max:255',
            'type' => 'nullable|max:255',
            'issued_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'auto_check' => 'nullable|boolean',
            'check_host' => 'nullable|max:255',
            'check_port' => 'nullable|integer|min:1|max:65535',
            'notes' => 'nullable',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Bezeichnung',
            'common_name' => 'Domain / CN',
            'issuer' => 'Aussteller',
            'type' => 'Typ',
            'issued_date' => 'Ausgestellt am',
            'expiry_date' => 'Ablaufdatum',
            'auto_check' => 'Automatisch prüfen',
            'check_host' => 'Prüfen an Host',
            'check_port' => 'Port',
            'notes' => 'Notizen',
        ];
    }
}
