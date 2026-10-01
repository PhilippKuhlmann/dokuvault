<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SipAccountRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'site_id' => ['nullable', new BelongsToCustomer('sites')],
            'provider' => 'required|max:255',
            'account_type' => ['nullable', Rule::in(array_keys(config('custom.sip_account_types')))],
            'product' => 'nullable|max:255',
            'contract_number' => 'nullable|max:255',
            'provider_customer_number' => 'nullable|max:255',
            'hotline' => 'nullable|max:255',
            'main_number' => 'nullable|max:255',
            'number_range' => 'nullable|max:255',
            'numbers' => 'nullable|max:5000',
            'channels' => 'nullable|integer|min:1|max:9999',
            'registrar' => 'nullable|max:255',
            'phone_system_id' => ['nullable', new BelongsToCustomer('phone_systems')],
            'internet_connection_id' => ['nullable', new BelongsToCustomer('internet_connections')],
            'notes' => 'nullable|max:5000',
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'provider' => 'Anbieter',
            'account_type' => 'Anschlussart',
            'product' => 'Produkt',
            'contract_number' => 'Vertragsnummer',
            'provider_customer_number' => 'Kundennummer',
            'hotline' => 'Hotline',
            'main_number' => 'Stammnummer',
            'number_range' => 'Durchwahlbereich',
            'numbers' => 'Rufnummern',
            'channels' => 'Sprachkanäle',
            'registrar' => 'SIP-Registrar',
            'phone_system_id' => 'TK-Anlage',
            'internet_connection_id' => 'Internetanschluss',
            'notes' => 'Notizen',
        ];
    }
}
