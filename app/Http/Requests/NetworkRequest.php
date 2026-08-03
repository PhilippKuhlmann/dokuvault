<?php

namespace App\Http\Requests;

use App\Rules\BelongsToCustomer;
use Illuminate\Foundation\Http\FormRequest;

class NetworkRequest extends FormRequest
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
            'site_id' => ['required', new BelongsToCustomer('sites')],
            'vlanId' => 'integer|min:1|max:4094',
            'description' => 'required',
            'network' => 'required|ipv4',
            'subnetmask' => 'required|ipv4',
            'cidr' => 'integer|min:0|max:32',
            'gateway' => 'nullable|ipv4',
            'dns1' => 'nullable|ipv4',
            'dns2' => 'nullable|ipv4',
            'dhcpStart' => 'nullable|ipv4',
            'dhcpEnd' => 'nullable|ipv4',
        ];
    }

    public function attributes()
    {
        return [
            'site_id' => 'Standort',
            'vlanId' => 'VLAN ID',
            'description' => 'Beschreibung',
            'network' => 'Netzwerk',
            'subnetmask' => 'Subnetzmaske',
            'cidr' => 'CIDR',
            'gateway' => 'Gateway',
            'dns1' => 'DNS 1',
            'dns2' => 'DNS 2',
            'dhcpStart' => 'DHCP-Start',
            'dhcpEnd' => 'DHCP-Ende',
        ];
    }
}
