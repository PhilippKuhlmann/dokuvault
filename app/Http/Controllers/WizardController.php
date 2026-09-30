<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DocumentationRun;
use Illuminate\Support\Facades\Gate;

class WizardController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorizeWizard();

        return view('wizard.index', compact('customer'));
    }

    /**
     * "Als erledigt markieren" vom Dashboard aus: schließt einen offenen Durchlauf
     * dieses Nutzers ab. Gibt es keinen (Einstieg "Starten"), wird ein bereits
     * abgeschlossener angelegt - das Dashboard fragt nur, ob es für den Kunden
     * einen abgeschlossenen Durchlauf gibt.
     */
    public function complete(Customer $customer)
    {
        $this->authorizeWizard();

        $closed = DocumentationRun::where('customer_id', $customer->id)
            ->where('user_id', auth()->id())
            ->whereNull('completed_at')
            ->update(['current_step' => null, 'completed_at' => now()]);

        if ($closed === 0) {
            DocumentationRun::create([
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'completed_steps' => [],
                'skipped_steps' => [],
                'completed_at' => now(),
            ]);
        }

        return redirect()->route('customer.dashboard', $customer);
    }

    protected function authorizeWizard(): void
    {
        // Feingranulare Prüfung (welche Schritte sichtbar sind) übernimmt
        // App\Livewire\DocumentationWizard beim mount() - hier nur der grobe Zugang:
        // ohne ein einziges _create-Recht aus den Assistenten-Schritten gibt es nichts zu tun.
        $hasAnyStepPermission = collect(config('custom.wizard_steps'))
            ->contains(fn (array $step) => Gate::allows($step['permission']));

        abort_unless($hasAnyStepPermission, 403);
    }
}
