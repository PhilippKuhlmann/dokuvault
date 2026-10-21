<?php

namespace App\Livewire;

use App\Livewire\Concerns\GehoertZumKunden;
use App\Livewire\Concerns\PrueftWaehrendDerEingabe;
use App\Models\LoginGeneral;
use App\Models\Scanner;
use App\Models\ScanTarget;
use App\Support\ScanTargetLinking;
use Illuminate\Support\Facades\Gate;
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
        return app(ScanTargetLinking::class)->rules($this->customerId, $this->neu);
    }

    protected function feldnamen(): array
    {
        return app(ScanTargetLinking::class)->attributeNames();
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

        app(ScanTargetLinking::class)->add($scanner, $validated, $this->neu);

        // Die Art bleibt stehen: Wer zwanzig Ordner eintraegt, will sie nicht
        // zwanzigmal neu waehlen.
        $this->reset('zielId', 'name', 'target', 'login_general_id');
        $this->dispatch('geraet-geaendert');
    }

    public function remove(int $id): void
    {
        app(ScanTargetLinking::class)->remove($this->scanner(), $id);
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
