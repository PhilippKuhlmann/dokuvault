<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SshKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|max:255',
            'description' => 'nullable|max:255',
            'username' => 'nullable|max:255',
            // Die Passphrase - inhaltlich das Kennwortfeld der Tabelle.
            'password' => 'nullable|max:255',
            'key_type' => ['nullable', Rule::in(array_keys(config('custom.ssh_key_types')))],
            // Kein max: Ein RSA-4096-Schluessel hat im privaten Teil ueber
            // 3000 Zeichen, ein Standardlimit von 255 wuerde ihn abschneiden.
            'public_key' => 'nullable|string',
            'private_key' => 'nullable|string',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Name',
            'description' => 'Beschreibung',
            'username' => 'Benutzername',
            'password' => 'Passphrase',
            'key_type' => 'Verfahren',
            'public_key' => 'Öffentlicher Schlüssel',
            'private_key' => 'Privater Schlüssel',
        ];
    }
}
