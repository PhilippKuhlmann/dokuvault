<?php

namespace App\Livewire;

use App\Livewire\Concerns\GehoertZumKunden;
use App\Models\Customer;
use App\Support\ChecklistBuilder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The customer reports a new employee: name, start, drives, programs,
 * programs. Next to the form stands what the checklist will contain, so the
 * customer sees what IT will do with the answers.
 */
class OnboardingForm extends Component
{
    use GehoertZumKunden;

    #[Locked]
    public int $customerId;

    public string $first_name = '';

    public string $last_name = '';

    public string $reference_date = '';

    /** Optional - empty means the suggestion (vorname.nachname). */
    public string $username = '';

    public string $email = '';

    public string $note = '';

    /** @var array<int|string, string> option id => '', 'r' or 'rw' */
    public array $drives = [];

    /** @var array<int, string> ids of ticked programs */
    public array $options = [];

    public function mount(Customer $customer): void
    {
        $this->nurEigenerKunde($customer->id);
        abort_unless(Gate::any(['checklist_request', 'checklist_manage']), 403);

        $this->customerId = $customer->id;
        $this->reference_date = now()->addWeeks(2)->toDateString();

        // "Kein" is a choice too - preselected, so every drive shows a state.
        foreach (ChecklistBuilder::onboardingShares($customer) as $share) {
            $this->drives[$share->id] = '';
        }
    }

    protected function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'reference_date' => ['required', 'date'],
            'username' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'drives' => ['array'],
            'drives.*' => ['nullable', 'in:r,rw'],
            'options' => ['array'],
            'options.*' => ['integer'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'first_name' => __('Vorname'),
            'last_name' => __('Nachname'),
            'reference_date' => __('Erster Arbeitstag'),
            'username' => __('Benutzername'),
            'email' => __('E-Mail-Adresse'),
        ];
    }

    public function submit(ChecklistBuilder $builder)
    {
        abort_unless(Gate::any(['checklist_request', 'checklist_manage']), 403);
        $this->nurEigenerKunde($this->customerId);

        $data = $this->validate();
        $customer = Customer::findOrFail($this->customerId);
        $checklist = $builder->startOnboarding($customer, $data, auth()->user());

        session()->flash('success', __('Gesendet. Die IT kümmert sich um :name.', ['name' => $checklist->fullName()]));

        return $this->redirectRoute('checklist.show', [$customer, $checklist]);
    }

    public function render()
    {
        $customer = Customer::findOrFail($this->customerId);
        $data = $this->only(['first_name', 'last_name', 'username', 'email', 'drives', 'options']);
        $preview = app(ChecklistBuilder::class)->onboardingItems($customer, $data);
        [$username, $email] = ChecklistBuilder::account($customer, $data);
        // The suggestion as placeholder - what an empty field will become.
        $suggestedUsername = ChecklistBuilder::username($this->first_name, $this->last_name);

        return view('livewire.onboarding-form', [
            'customer' => $customer,
            'shares' => ChecklistBuilder::onboardingShares($customer),
            'choices' => ChecklistBuilder::optionsByKind($customer),
            'preview' => $preview->groupBy('section'),
            'previewCount' => $preview->count(),
            'approvals' => $preview->where('needs_approval', true)->count(),
            'username' => $username,
            'email' => $email,
            'suggestedUsername' => $suggestedUsername ?: 'vorname.nachname',
            'suggestedEmail' => ChecklistBuilder::email($customer, $suggestedUsername ?: 'vorname.nachname'),
        ]);
    }
}
