<?php

namespace App\Livewire;

use App\Livewire\Concerns\ScopedToCustomer;
use App\Livewire\Concerns\ValidatesOnInput;
use App\Models\LoginGeneral;
use App\Models\Scanner;
use App\Models\ScanTarget;
use App\Support\ScanTargetLinking;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The scan targets of a scanner, in the edit form. Saves at once, like
 * "Weitere IP-Adressen" beside it.
 *
 * A target belongs to the customer, not to the scanner: here one is linked or
 * a new one is created and linked right away. Releasing takes only the link
 * away - the same target can hang on another scanner.
 */
class ScanTargets extends Component
{
    use ScopedToCustomer;
    use ValidatesOnInput;

    #[Locked]
    public int $scannerId;

    #[Locked]
    public int $customerId;

    /** An existing target to link. */
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

    protected function fieldNames(): array
    {
        return app(ScanTargetLinking::class)->attributeNames();
    }

    public function mount($model, $customer, bool $eingebettet = false, bool $randlos = false): void
    {
        $this->onlyOwnCustomer($customer->id);
        abort_if($model->customer_id !== $customer->id, 403);

        $this->scannerId = $model->id;
        $this->customerId = $customer->id;
        $this->eingebettet = $eingebettet;
        $this->randlos = $randlos;

        // If there is nothing to link yet, the block starts on creating.
        // Only when opening - after that the switch decides.
        $this->neu = Gate::allows('scantarget_create')
            && ! ScanTarget::where('customer_id', $customer->id)->exists();
    }

    /**
     * Check permission and customer anew on every action - public properties
     * can be changed on the client.
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

        // Creating a new target is more than linking: an entry comes into
        // being in the list of scan targets.
        if ($this->neu) {
            Gate::authorize('scantarget_create');
        }

        $this->enableCheck();
        $validated = $this->validate($this->regeln(), [], $this->fieldNames());

        app(ScanTargetLinking::class)->add($scanner, $validated, $this->neu);

        // The kind stays: whoever enters twenty folders does not want to
        // zwanzigmal neu waehlen.
        $this->reset('zielId', 'name', 'target', 'login_general_id');
        $this->dispatch('device-changed');
    }

    public function remove(int $id): void
    {
        app(ScanTargetLinking::class)->remove($this->scanner(), $id);
        $this->dispatch('device-changed');
    }

    public function render()
    {
        $verknuepft = Scanner::find($this->scannerId)?->scanTargets()->with('login')->get() ?? collect();

        // On offer only what is not linked yet - and only for whoever may see
        // the list of targets.
        $vorhandene = Gate::allows('scantarget_viewAny')
            ? ScanTarget::where('customer_id', $this->customerId)
                ->whereNotIn('id', $verknuepft->pluck('id'))
                ->orderBy('name')->get()
            : collect();
        $darfAnlegen = Gate::allows('scantarget_create');
        $darfVerknuepfen = Gate::allows('scantarget_viewAny');

        // Only one of the two options: then that one straight away, without a switch.
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
