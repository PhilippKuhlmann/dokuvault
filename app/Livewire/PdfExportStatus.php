<?php

namespace App\Livewire;

use App\Jobs\GenerateCustomerPdf;
use App\Livewire\Concerns\ScopedToCustomer;
use App\Models\Customer;
use App\Models\PdfExport;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Button and state of the PDF export.
 *
 * The PDF comes into being in the background - without this display there
 * would be no telling after the click whether anything is happening. It is
 * polled only while a job runs; once it is finished the polling stops.
 */
class PdfExportStatus extends Component
{
    use ScopedToCustomer;

    #[Locked]
    public int $customerId;

    public function mount(Customer $customer): void
    {
        $this->onlyOwnCustomer($customer->id);

        $this->customerId = $customer->id;
    }

    public function start(): void
    {
        Gate::authorize('create_pdf');

        $kunde = Customer::findOrFail($this->customerId);

        // No second job while one runs: whoever clicks twice should not set
        // the same mountain of work going twice.
        if ($this->auftrag()?->laeuftNoch()) {
            return;
        }

        $export = PdfExport::create([
            'customer_id' => $kunde->id,
            'user_id' => auth()->id(),
            'status' => PdfExport::OFFEN,
        ]);

        GenerateCustomerPdf::dispatch($export->id);

        $this->dispatch('notice', text: __('PDF wird erstellt.'));
    }

    /**
     * The last job of this user for this customer.
     *
     * Per user, not per customer: the PDF holds every credential, so only the
     * person who ordered it may collect it.
     */
    public function auftrag(): ?PdfExport
    {
        return PdfExport::where('customer_id', $this->customerId)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();
    }

    public function render()
    {
        $auftrag = $this->auftrag();

        return view('livewire.pdf-export-status', [
            'auftrag' => $auftrag,
            'kunde' => Customer::find($this->customerId),
            // Only poll while something runs - and not endlessly when nobody
            // works the queue.
            'nachfragen' => (bool) $auftrag?->laeuftNoch() && ! $auftrag->haengt(),
        ]);
    }
}
