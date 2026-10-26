<?php

namespace App\Livewire;

use App\Livewire\Concerns\GehoertZumKunden;
use App\Livewire\Concerns\PrueftWaehrendDerEingabe;
use App\Models\Customer;
use App\Models\Network;
use App\Support\IpRangeEditing;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Reservierte Bereiche eines Netzes - anlegen und wieder loeschen.
 *
 * Sitzt am Netz, nicht in einer eigenen Liste im Menue: Ein Bereich ergibt nur
 * dort Sinn, wo man ihn sieht. Dasselbe Muster wie die IP-Adressen und die
 * Zugangsdaten am Geraet.
 *
 * Ein Bereich belegt nichts. Er haelt fest, wofuer ein Stueck des Netzes
 * gedacht ist - "10.10.250.10 bis .20 sind fuer die Proxmox-Server" -, auch
 * wenn davon erst zwei Adressen vergeben sind.
 */
class IpBereiche extends Component
{
    use GehoertZumKunden;
    use PrueftWaehrendDerEingabe;

    // Locked: set in mount() after the tenant check. Changed in the browser,
    // they pointed every action at another customer's network.
    #[Locked]
    public int $customerId;

    #[Locked]
    public int $networkId;

    public bool $offen = false;

    public string $from_ip = '';

    public string $to_ip = '';

    public string $label = '';

    public string $note = '';

    public function mount(Customer $customer, Network $network): void
    {
        // Zwei verschiedene Fragen, beide noetig: Darf dieser Benutzer diesen
        // Kunden sehen - und gehoert dieses Netz zu ihm? Ohne die erste kaeme
        // ein auf einen Kunden festgelegter Nutzer an fremde Netzplanung.
        $this->nurEigenerKunde($customer->id);

        abort_unless($network->customer_id === $customer->id, 404);

        $this->customerId = $customer->id;
        $this->networkId = $network->id;
    }

    /** Die Regeln stehen in IpRangeEditing - dieselben fuer die iOS-App. */
    protected function regeln(): array
    {
        return app(IpRangeEditing::class)->rules();
    }

    protected function feldnamen(): array
    {
        return app(IpRangeEditing::class)->attributeNames();
    }

    public function oeffnen(): void
    {
        Gate::authorize('network_update');

        $this->reset('from_ip', 'to_ip', 'label', 'note', 'geprueft');
        $this->resetErrorBag();
        $this->offen = true;
    }

    public function abbrechen(): void
    {
        $this->reset('offen', 'from_ip', 'to_ip', 'label', 'note', 'geprueft');
        $this->resetErrorBag();
    }

    public function speichern(): void
    {
        Gate::authorize('network_update');

        $this->pruefungEinschalten();
        $daten = $this->validate($this->regeln(), [], $this->feldnamen());

        app(IpRangeEditing::class)->create($this->netz(), $daten);

        $this->abbrechen();

        // Die Adressliste darueber zeichnet der Controller, nicht diese
        // Komponente - ohne den Neuaufbau bliebe der neue Bereich unsichtbar,
        // und genau ihn wollte man ja sehen.
        $this->redirect(request()->header('Referer') ?: route('ipplan.index', $this->customerId), navigate: true);
    }

    public function loeschen(int $id): void
    {
        Gate::authorize('network_update');

        // Nur Bereiche dieses Netzes - eine untergeschobene fremde Id waere
        // sonst loeschbar.
        app(IpRangeEditing::class)->delete($this->netz(), $id);

        $this->redirect(request()->header('Referer') ?: route('ipplan.index', $this->customerId), navigate: true);
    }

    private function netz(): Network
    {
        // Every action and every render binds to the user's customer again -
        // not only mount(). The ids are locked as well, see above.
        $this->nurEigenerKunde($this->customerId);

        return Network::where('customer_id', $this->customerId)->findOrFail($this->networkId);
    }

    public function render()
    {
        return view('livewire.ip-bereiche', [
            'bereiche' => app(IpRangeEditing::class)->ranges($this->netz()),
            'darfPflegen' => Gate::allows('network_update'),
        ]);
    }
}
