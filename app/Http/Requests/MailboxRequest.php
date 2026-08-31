<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MailboxRequest extends FormRequest
{
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
            'name' => 'max:255',
            'mailAdress' => 'email|max:255',
            'username' => 'max:255',
            'password' => 'nullable|max:255',
            'mailbox_provider_id' => '',
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Name',
            'mailAdress' => 'E-Mail Adresse',
            'username' => 'Benutzername',
            'password' => 'Passwort',
            'mailbox_provider_id' => 'Anbieter',
        ];
    }
}
