<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Support\Facades\Gate;

class WizardController extends Controller
{
    public function index(Customer $customer)
    {
        // Feingranulare Prüfung (welche Schritte sichtbar sind) übernimmt
        // App\Livewire\DocumentationWizard beim mount() - hier nur der grobe Zugang:
        // ohne ein einziges _create-Recht aus den Assistenten-Schritten gibt es nichts zu tun.
        $hasAnyStepPermission = collect(config('custom.wizard_steps'))
            ->contains(fn (array $step) => Gate::allows($step['permission']));

        abort_unless($hasAnyStepPermission, 403);

        return view('wizard.index', compact('customer'));
    }
}
