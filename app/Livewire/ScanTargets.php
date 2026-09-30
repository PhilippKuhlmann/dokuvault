<?php

namespace App\Livewire;

use App\Livewire\Concerns\GehoertZumKunden;
use App\Livewire\Concerns\PrueftWaehrendDerEingabe;
use App\Models\LoginGeneral;
use App\Models\Scanner;
use App\Models\ScanTarget;
use App\Rules\BelongsToCustomer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Die Scan-Ziele eines Scanners, im Bearbeiten-Formular. Speichert sofort,
 * wie "Weitere IP-Adressen" daneben.
 *
 * Ein Ziel gehoert dem Kunden, nicht dem Scanner: Hier wird verknuepft oder
 * ein neues angelegt und gleich verknuepft. Loesen nimmt nur die Verknuepfung
 * weg - dasselbe Ziel kann an einem anderen Scanner haengen.
 */
class ScanTargets extends Component
{
    use GehoertZumKunden;
    use PrueftWaehrendDerEingabe;

    #[Locked]
    public int $scannerId;

    #[Locked]
    public int $customerId;

    /** Vorhandenes Ziel zum Verknuepfen. */
    public $zielId = '';

    /** Neues Ziel. */
    public bool $neu = false;

    public string $name = '';

    public string $kind = 'smb';

    public string $target = '';

    public $login_general_id = '';

    #[Locked]
    public bool $eingebettet = false;

    public bool $randlos = false;

    protected function regeln(): array
    {
        if (! $this->neu) {
            return [
                'zielId' => ['required', Rule::exists('scan_targets', 'id')
                    ->where('customer_id', $this->customerId)->whereNull('deleted_at')],
            ];
        }

        return [
            'name' => ['nullable', 'max:255'],
            'kind' => ['required', Rule::in(array_keys(ScanTarget::ARTEN))],
            'target' => ['required', 'max:255'],
            'login_general_id' => ['nullable', new BelongsToCustomer('login_generals', $this->customerId)],
        ];
    }

    protected function feldnamen(): array
    {
        return [
            'zielId' => __('Scan-Ziel'), 'name' => __('Bezeichnung'), 'kind' => __('Art'),
            'target' => __('Ziel'), 'login_general_id' => __('Zugangsdaten'),
        ];
    }

    public function mount($model, $customer, bool $eingebettet = false, bool $randlos = false): void
    {
        $this->nurEigenerKunde($customer->id);
        abort_if($model->customer_id !== $customer->id, 403);

        $this->scannerId = $model->id;
        $this->customerId = $customer->id;
        $this->eingebettet = $eingebettet;
        $this->randlos = $randlos;

        // Gibt es noch nichts zu verknuepfen, beginnt der Block beim Anlegen.
        // Nur beim Oeffnen - danach entscheidet der Umschalter.
        $this->neu = Gate::allows('scantarget_create')
            && ! ScanTarget::where('customer_id', $customer->id)->exists();
    }

    /**
     * Recht und Kunde bei jeder Aktion neu pruefen - Public Properties sind
     * client-seitig veraenderbar.
     */
    protected function scanner(): Scanner
    {
        $scanner = Scanner::findOrFail($this->scannerId);

        Gate::authorize('scanner_update');

        $user = auth()->user();
        abort_if($user->customer_id && $user->customer_id !== $scanner->customer_id, 403);
        abort_if($scanner->customer_id !== $this->customerId, 403);

        return $scanner;
    }

    public function add(): void
    {
        $scanner = $this->scanner();

        // Ein neues Ziel anlegen ist mehr als verknuepfen: Es entsteht ein
        // Eintrag in der Liste der Scan-Ziele.
        if ($this->neu) {
            Gate::authorize('scantarget_create');
        }

        $this->pruefungEinschalten();
        $validated = $this->validate($this->regeln(), [], $this->feldnamen());

        $ziel = $this->neu
            ? ScanTarget::create([
                'customer_id' => $this->customerId,
                'name' => $validated['name'] ?: null,
                'kind' => $validated['kind'],
                'target' => $validated['target'],
                'login_general_id' => $validated['login_general_id'] ?: null,
            ])
            : ScanTarget::findOrFail($validated['zielId']);

        $scanner->scanTargets()->syncWithoutDetaching([$ziel->id]);

        // Die Art bleibt stehen: Wer zwanzig Ordner eintraegt, will sie nicht
        // zwanzigmal neu waehlen.
        $this->reset('zielId', 'name', 'target', 'login_general_id');
        $this->dispatch('geraet-geaendert');
    }

    public function remove(int $id): void
    {
        $this->scanner()->scanTargets()->detach($id);
        $this->dispatch('geraet-geaendert');
    }

    public function render()
    {
        $verknuepft = Scanner::find($this->scannerId)?->scanTargets()->with('login')->get() ?? collect();

        // Zur Auswahl nur, was noch nicht verknuepft ist - und nur fuer den,
        // der die Liste der Ziele sehen darf.
        $vorhandene = Gate::allows('scantarget_viewAny')
            ? ScanTarget::where('customer_id', $this->customerId)
                ->whereNotIn('id', $verknuepft->pluck('id'))
                ->orderBy('name')->get()
            : collect();
        $darfAnlegen = Gate::allows('scantarget_create');
        $darfVerknuepfen = Gate::allows('scantarget_viewAny');

        // Nur eine der beiden Moeglichkeiten: dann ohne Umschalter gleich die.
        if (! $darfVerknuepfen && $darfAnlegen) {
            $this->neu = true;
        }

        return view('livewire.scan-targets', [
            'eintraege' => $verknuepft,
            'vorhandene' => $vorhandene,
            'darfAnlegen' => $darfAnlegen,
            'darfVerknuepfen' => $darfVerknuepfen,
            'logins' => Gate::allows('logingeneral_viewAny')
                ? LoginGeneral::where('customer_id', $this->customerId)->orderBy('name')->get()
                : collect(),
            'arten' => ScanTarget::ARTEN,
        ]);
    }
}
