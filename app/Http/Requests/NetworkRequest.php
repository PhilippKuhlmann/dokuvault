<?php

namespace App\Http\Requests;

use App\Models\Network;
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
     * Fehlt eine der beiden Schreibweisen, wird sie aus der anderen ergaenzt.
     *
     * Im VLAN-Modal und im Assistenten passiert das schon bei der Eingabe.
     * Das alte Formular unter /network/create ist kein Livewire - dort faellt
     * die Ergaenzung hier an, damit nicht die halbe Angabe gespeichert wird.
     * Sind beide gefuellt, bleiben beide stehen: Ein Widerspruch ist eine
     * Eingabe und keine Luecke, die zu ueberschreiben waere anmassend.
     */
    protected function prepareForValidation(): void
    {
        $maske = $this->input('subnetmask');
        $cidr = $this->input('cidr');

        if (blank($cidr) && filled($maske)) {
            $this->merge(['cidr' => Network::cidrAusMaske($maske)]);
        }

        if (blank($maske) && filled($cidr)) {
            $this->merge(['subnetmask' => Network::maskeAusCidr($cidr)]);
        }
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
