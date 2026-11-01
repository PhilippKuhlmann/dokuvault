<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\WizardFlow;
use Illuminate\Support\Facades\Gate;

class WizardController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorizeWizard();

        return view('wizard.index', compact('customer'));
    }

    /**
     * "Mark as done" from the dashboard: closes an open run of this user. If
     * there is none (entry through "start"), one that is already closed is
     * created - the dashboard only asks whether there is one for the customer
     * einen abgeschlossenen Durchlauf gibt.
     */
    public function complete(Customer $customer)
    {
        $this->authorizeWizard();

        (new WizardFlow($customer, auth()->user()))->abschliessen();

        return redirect()->route('customer.dashboard', $customer);
    }

    protected function authorizeWizard(): void
    {
        // The fine-grained check (which steps are visible) is done by
        // App\Livewire\DocumentationWizard in mount() - here only the coarse
        // access: without a single _create permission from the wizard steps there is nothing to
        $hasAnyStepPermission = collect(config('custom.wizard_steps'))
            ->contains(fn (array $step) => Gate::allows($step['permission']));

        abort_unless($hasAnyStepPermission, 403);
    }
}
