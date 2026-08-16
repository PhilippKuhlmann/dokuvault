<?php

namespace App\Livewire;

use App\Models\Network;
use App\Models\Site;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
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

    /** Gesetzt heisst: bearbeiten statt anlegen. */
    public ?int $bearbeiteId = null;

    /** Rueckfrage vor dem Loeschen - als eigene Ansicht statt als Browser-Dialog. */
    public bool $loeschenGefragt = false;

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

    public function mount($customer, ?int $siteId = null, string $knopfKlassen = '', string $label = '', bool $mitSymbol = false): void
    {
        $this->customerId = $customer->id;
        $this->siteId = $siteId;
        $this->knopfKlassen = $knopfKlassen;
        $this->label = $label;
        $this->mitSymbol = $mitSymbol;
    }

    /**
     * Stift in der Liste: dasselbe Modal, nur mit geladenen Werten.
     *
     * Das Netz wird gegen den Kunden geprueft - die ID kommt aus dem Browser,
     * und ohne die Pruefung liesse sich damit ein fremdes Netz oeffnen.
     */
    #[On('vlan-bearbeiten')]
    public function bearbeiten(int $id): void
    {
        Gate::authorize('network_update');

        $netz = Network::where('customer_id', $this->customerId)->findOrFail($id);

        $this->bearbeiteId = $netz->id;
        $this->site_id = $netz->site_id;
        $this->description = (string) $netz->description;
        $this->vlanId = $netz->vlanId;
        $this->network = (string) $netz->network;
        $this->subnetmask = (string) $netz->subnetmask;
        $this->cidr = $netz->cidr;
        $this->gateway = (string) $netz->gateway;
        $this->dns1 = (string) $netz->dns1;
        $this->dns2 = (string) $netz->dns2;
        $this->dhcpStart = (string) $netz->dhcpStart;
        $this->dhcpEnd = (string) $netz->dhcpEnd;

        $this->resetErrorBag();
        $this->loeschenGefragt = false;
        $this->offen = true;
    }

    public function speichern(): void
    {
        Gate::authorize($this->bearbeiteId ? 'network_update' : 'network_create');

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
        if (! $this->siteId || $this->bearbeiteId) {
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

        $werte = [
            'site_id' => $this->siteId && ! $this->bearbeiteId ? $this->siteId : $daten['site_id'],
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
        ];

        if ($this->bearbeiteId) {
            // Erneut gegen den Kunden geprueft: bearbeiteId ist zwischen dem
            // Oeffnen und dem Speichern manipulierbar.
            $netz = Network::where('customer_id', $this->customerId)->findOrFail($this->bearbeiteId);
            $netz->update($werte);
        } else {
            $netz = Network::create($werte + ['customer_id' => $this->customerId]);
        }

        $this->dispatch('hinweis', text: $this->bearbeiteId
            ? __('VLAN gespeichert.')
            : __('VLAN angelegt.'));

        // Erst nach der Meldung: bearbeiteId entscheidet ueber ihren Wortlaut.
        $this->reset('offen', 'bearbeiteId', 'loeschenGefragt', 'site_id', 'description',
            'vlanId', 'network', 'cidr', 'gateway', 'dns1', 'dns2', 'dhcpStart', 'dhcpEnd');
        $this->subnetmask = '255.255.255.0';

        // Der IP-Block waehlt das neue Netz damit gleich aus, die Liste rendert
        // neu. Kein Redirect mehr: Seit die Liste selbst Livewire ist, genuegt
        // das Event - das erspart den Seitenwechsel samt seiner Fallstricke
        // (405 auf der Update-Adresse, verlorener Dunkelmodus).
        $this->dispatch('vlan-angelegt', id: $netz->id);
    }

    /**
     * Loeschen aus dem Bearbeiten-Modal - im eigenen Formular gab es dafuer die
     * Loeschen-Karte, hier fehlte der Weg.
     *
     * Wie beim Speichern gegen den Kunden geprueft; die ID kommt aus dem
     * Browser. Das Netz landet im Papierkorb (SoftDelete), verknuepfte
     * IP-Adressen behalten ihre network_id und laufen ins Leere - dasselbe
     * Verhalten wie beim Loeschen ueber die alte Seite.
     */
    public function loeschen(): void
    {
        Gate::authorize('network_delete');

        if (! $this->bearbeiteId) {
            return;
        }

        Network::where('customer_id', $this->customerId)
            ->findOrFail($this->bearbeiteId)
            ->delete();

        $this->reset('offen', 'bearbeiteId', 'loeschenGefragt', 'site_id', 'description', 'vlanId', 'network',
            'cidr', 'gateway', 'dns1', 'dns2', 'dhcpStart', 'dhcpEnd');
        $this->subnetmask = '255.255.255.0';

        $this->dispatch('hinweis', text: __('VLAN gelöscht.'));
        $this->dispatch('vlan-angelegt', id: 0);
    }

    public function render()
    {
        return view('livewire.network-quick-create', [
            // Nur wenn kein Standort vorgegeben ist - sonst waere die Liste unnoetig.
            // Beim Bearbeiten immer zeigen: Der Standort eines bestehenden
            // Netzes soll aenderbar sein, auch wenn das Modal am Geraet haengt.
            'sites' => $this->siteId && ! $this->bearbeiteId
                ? collect()
                : Site::where('customer_id', $this->customerId)->orderBy('name')->get(),
        ]);
    }
}
