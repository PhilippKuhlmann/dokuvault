<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|max:255',
            'software' => 'max:255',
            'source' => 'max:255',
            'destination' => 'max:255',
            'schedule' => 'max:255',
            'retention' => 'max:255',
            'last_success' => 'nullable|date',
            'password' => 'nullable|max:255',
            'notes' => 'nullable',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'Name', 'software' => 'Software', 'source' => 'Quelle', 'destination' => 'Ziel', 'schedule' => 'Zeitplan', 'retention' => 'Aufbewahrung', 'last_success' => 'Letzter Erfolg', 'password' => 'Passwort', 'notes' => 'Notizen'];
    }
}
