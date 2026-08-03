<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;

class InternetConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'provider' => 'required|max:255',
            'product' => 'max:255',
            'contract_number' => 'max:255',
            'connection_type' => 'max:255',
            'bandwidth_down' => 'max:255',
            'bandwidth_up' => 'max:255',
            'wan_ip' => 'max:255',
            'hotline' => 'max:255',
            'notes' => 'nullable',
        ];
    }

    public function attributes(): array
    {
        return ['site_id' => 'Standort', 'provider' => 'Anbieter', 'product' => 'Produkt', 'contract_number' => 'Vertragsnummer', 'connection_type' => 'Anschlussart', 'bandwidth_down' => 'Download', 'bandwidth_up' => 'Upload', 'wan_ip' => 'WAN-IP', 'hotline' => 'Hotline', 'notes' => 'Notizen'];
    }
}
