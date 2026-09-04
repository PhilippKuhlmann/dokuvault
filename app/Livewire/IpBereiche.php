<?php

namespace App\Livewire;

use App\Livewire\Concerns\GehoertZumKunden;
use App\Livewire\Concerns\PrueftWaehrendDerEingabe;
use App\Models\Customer;
use App\Models\IpRange;
use App\Models\Network;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

    public int $customerId;

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

    protected function regeln(): array
    {
        return [
            'from_ip' => ['required', 'ipv4'],
            'to_ip' => ['required', 'ipv4'],
            'label' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function feldnamen(): array
    {
        return [
            'from_ip' => __('Von'),
            'to_ip' => __('Bis'),
            'label' => __('Wofür'),
            'note' => __('Notiz'),
        ];
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
        $this->validate($this->regeln(), [], $this->feldnamen());

        $netz = $this->netz();
        $von = IpRange::alsLong($this->from_ip);
        $bis = IpRange::alsLong($this->to_ip);

        // Die Reihenfolge zuerst: Ohne sie waere jede weitere Meldung Rauschen.
        if ($bis < $von) {
            throw ValidationException::withMessages([
                'to_ip' => __('Die Endadresse liegt vor der Anfangsadresse.'),
            ]);
        }

        // Im Netz, nicht daneben. Geprueft ueber dieselbe Funktion, mit der die
        // Anwendung sonst entscheidet, ob eine Adresse zu einem Netz gehoert.
        foreach (['from_ip' => $this->from_ip, 'to_ip' => $this->to_ip] as $feld => $adresse) {
            if (! $netz->enthaeltAdresse($adresse)) {
                throw ValidationException::withMessages([
                    $feld => __('Diese Adresse liegt nicht in :netz.', ['netz' => $netz->anzeige() ?: $netz->network]),
                ]);
            }
        }

        // Zwei Reservierungen fuer dieselbe Adresse waeren ein Widerspruch in
        // der Doku, kein Zustand, den man abbilden will.
        $ueberschneidung = $this->bereiche()->first(
            fn (IpRange $b) => $b->vonLong() !== null && $b->bisLong() !== null
                && $von <= $b->bisLong() && $bis >= $b->vonLong()
        );

        if ($ueberschneidung) {
            throw ValidationException::withMessages([
                'from_ip' => __('Überschneidet sich mit „:label" (:von – :bis).', [
                    'label' => $ueberschneidung->label,
                    'von' => $ueberschneidung->from_ip,
                    'bis' => $ueberschneidung->to_ip,
                ]),
            ]);
        }

        IpRange::create([
            'customer_id' => $this->customerId,
            'network_id' => $this->networkId,
            'from_ip' => $this->from_ip,
            'to_ip' => $this->to_ip,
            'label' => trim($this->label),
            'note' => trim($this->note) ?: null,
        ]);

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
        $this->bereiche()->firstWhere('id', $id)?->delete();

        $this->redirect(request()->header('Referer') ?: route('ipplan.index', $this->customerId), navigate: true);
    }

    private function netz(): Network
    {
        return Network::where('customer_id', $this->customerId)->findOrFail($this->networkId);
    }

    private function bereiche()
    {
        return IpRange::where('network_id', $this->networkId)
            ->where('customer_id', $this->customerId)
            ->orderBy('from_ip')
            ->get();
    }

    public function render()
    {
        return view('livewire.ip-bereiche', [
            'bereiche' => $this->bereiche(),
            'darfPflegen' => Gate::allows('network_update'),
        ]);
    }
}
