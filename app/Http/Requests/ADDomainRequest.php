<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ADDomainRequest extends FormRequest
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
            'domain' => 'required|max:255',
            'netbios' => 'required|max:255',
            'dsrmpassword' => 'required|max:255',
            'functional_level' => ['nullable', Rule::in(array_keys(config('custom.ad_functional_levels')))],
            'upn_suffixes' => 'nullable|max:255',
            'fsmo_holder' => 'nullable|max:255',
            'dns_forwarders' => 'nullable|max:255',
            'dhcp_server' => 'nullable|max:255',
            'entra_connect' => 'nullable|boolean',
            'notes' => 'nullable|max:5000',
            // Domain controllers, Entra Connect and CA are 'host' fields: their
            // rules come from ObjektFormular, which knows the customer's machines.
        ];
    }

    public function attributes()
    {
        return [
            'domain' => 'Domäne',
            'netbios' => 'NETBIOS',
            'dsrmpassword' => 'DSRM Passwort',
            'functional_level' => 'Funktionsebene',
            'upn_suffixes' => 'UPN-Suffixe',
            'fsmo_holder' => 'FSMO-Rollen',
            'dns_forwarders' => 'DNS-Weiterleitungen',
            'dhcp_server' => 'DHCP-Server',
            'entra_connect' => 'Entra Connect',
            'notes' => 'Notizen',
        ];
    }
}
