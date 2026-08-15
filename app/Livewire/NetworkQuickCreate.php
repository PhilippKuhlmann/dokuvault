<?php

namespace App\Livewire;

use App\Models\Network;
use App\Models\Site;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * VLAN anlegen, ohne die Seite zu wechseln.
 *
 * Zwei Einsatzorte: im Block "Weitere IP-Adressen" am Geraet (dort merkt man
 * beim Eintragen einer Adresse, dass das Netz fehlt) und ueber der VLAN-Liste.
 * Deshalb eine eigene Komponente statt zweier Kopien - dasselbe Formular an
 * zwei Stellen zu pflegen ginge schief.
 *
 * Der Standort kommt vom aufrufenden Geraet, wenn es eins gibt; sonst waehlt
 * man ihn im Modal. customer_id kommt immer aus der Komponente, nie aus dem
 * Formular.
 */
class NetworkQuickCreate extends Component
{
    #[Locked]
    public int $customerId;

    /** Vom Geraet vorgegeben - dann entfaellt die Auswahl im Modal. */
    #[Locked]
    public ?int $siteId = null;

    public bool $offen = false;

    public $site_id = '';

    public string $description = '';

    public $vlanId = '';

    public string $network = '';

    public string $subnetmask = '255.255.255.0';

    public $cidr = '';

    public string $gateway = '';

    public string $dns1 = '';

    public string $dns2 = '';

    public string $dhcpStart = '';

    public string $dhcpEnd = '';

    /** Aussehen des ausloesenden Knopfes - Textlink am Geraet, voller Knopf in der Liste. */
    public string $knopfKlassen = '';

    public string $label = '';

    public bool $mitSymbol = false;

    /**
     * In der VLAN-Liste die Seite neu laden. Die Liste ist eine normale
     * Blade-Seite - nach dem Speichern rendert nur diese Komponente neu, das
     * neue Netz stuende sonst erst nach einem Neuladen da. Am Geraet ist das
     * unnoetig: Dort wird es nur in der Auswahl gesetzt.
     */
    public bool $neuLaden = false;

    public function mount($customer, ?int $siteId = null, string $knopfKlassen = '', string $label = '', bool $mitSymbol = false, bool $neuLaden = false): void
    {
        $this->customerId = $customer->id;
        $this->siteId = $siteId;
        $this->knopfKlassen = $knopfKlassen;
        $this->label = $label;
        $this->mitSymbol = $mitSymbol;
        $this->neuLaden = $neuLaden;
    }

    public function speichern(): void
    {
        Gate::authorize('network_create');

        $regeln = [
            'description' => ['required', 'max:255'],
            'vlanId' => ['nullable', 'integer', 'min:1', 'max:4094'],
            'network' => ['required', 'ipv4'],
            'subnetmask' => ['required', 'ipv4'],
            'cidr' => ['nullable', 'integer', 'min:0', 'max:32'],
            'gateway' => ['nullable', 'ipv4'],
            'dns1' => ['nullable', 'ipv4'],
            'dns2' => ['nullable', 'ipv4'],
            'dhcpStart' => ['nullable', 'ipv4'],
            'dhcpEnd' => ['nullable', 'ipv4'],
        ];

        // Ohne vorgegebenen Standort muss einer gewaehlt werden - und zwar einer
        // dieses Kunden, sonst haengt das Netz an einem fremden Standort.
        if (! $this->siteId) {
            $regeln['site_id'] = ['required', Rule::exists('sites', 'id')
                ->where('customer_id', $this->customerId)->whereNull('deleted_at')];
        }

        $daten = $this->validate($regeln, [], [
            'site_id' => __('Standort'),
            'description' => __('Bezeichnung'),
            'vlanId' => __('VLAN-ID'),
            'network' => __('Netz'),
            'subnetmask' => __('Subnetzmaske'),
            'cidr' => __('CIDR'),
            'gateway' => __('Gateway'),
            'dns1' => __('DNS 1'),
            'dns2' => __('DNS 2'),
            'dhcpStart' => __('DHCP-Start'),
            'dhcpEnd' => __('DHCP-Ende'),
        ]);

        $netz = Network::create([
            'customer_id' => $this->customerId,
            'site_id' => $this->siteId ?: $daten['site_id'],
            'description' => $daten['description'],
            'vlanId' => $daten['vlanId'] ?: null,
            'network' => $daten['network'],
            'subnetmask' => $daten['subnetmask'],
            'cidr' => $daten['cidr'] ?: null,
            'gateway' => $daten['gateway'] ?: null,
            'dns1' => $daten['dns1'] ?: null,
            'dns2' => $daten['dns2'] ?: null,
            'dhcpStart' => $daten['dhcpStart'] ?: null,
            'dhcpEnd' => $daten['dhcpEnd'] ?: null,
        ]);

        $this->reset('offen', 'site_id', 'description', 'vlanId', 'network',
            'cidr', 'gateway', 'dns1', 'dns2', 'dhcpStart', 'dhcpEnd');
        $this->subnetmask = '255.255.255.0';

        // Der IP-Block waehlt das neue Netz damit gleich aus.
        $this->dispatch('vlan-angelegt', id: $netz->id);

        if ($this->neuLaden) {
            $this->redirect(url()->current(), navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.network-quick-create', [
            // Nur wenn kein Standort vorgegeben ist - sonst waere die Liste unnoetig.
            'sites' => $this->siteId
                ? collect()
                : Site::where('customer_id', $this->customerId)->orderBy('name')->get(),
        ]);
    }
}
