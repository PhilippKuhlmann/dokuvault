<?php

namespace App\Livewire;

use App\Livewire\Concerns\GehoertZumKunden;
use App\Models\Checklist;
use App\Models\ChecklistSetting;
use App\Models\Customer;
use App\Models\NetworkShare;
use App\Support\ChecklistAdScript;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * A running checklist: IT ticks items off, the customer's management approves
 * sensitive drives. An item waiting for approval cannot be ticked.
 */
class ChecklistProgress extends Component
{
    use GehoertZumKunden;

    public int $customerId;

    public int $checklistId;

    public string $username = '';

    public string $email = '';

    /** AD script: force a password change at first sign-in. Off unless IT ticks it. */
    public bool $forcePasswordChange = false;

    public function mount(Customer $customer, Checklist $checklist): void
    {
        $this->nurEigenerKunde($customer->id);
        abort_unless($checklist->customer_id === $customer->id, 404);

        $this->customerId = $customer->id;
        $this->checklistId = $checklist->id;
        $this->username = (string) $checklist->username;
        $this->email = (string) $checklist->email;
        $this->forcePasswordChange = (bool) $checklist->force_password_change;
    }

    public function updatedForcePasswordChange(bool $value): void
    {
        Gate::authorize('checklist_manage');

        $this->checklist()->update(['force_password_change' => $value]);
    }

    /**
     * IT settles username and email. Items that name the old values
     * ("AD-Benutzer sophie.kraemer anlegen") follow.
     */
    public function saveAccount(): void
    {
        Gate::authorize('checklist_manage');

        $data = $this->validate([
            'username' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [], ['username' => __('Benutzername'), 'email' => __('E-Mail-Adresse')]);

        $checklist = $this->checklist();
        $replace = array_filter([
            (string) $checklist->email => $data['email'] ?: null,
            (string) $checklist->username => $data['username'] ?: null,
        ], fn ($new, $old) => $old !== '' && $new !== null && $new !== $old, ARRAY_FILTER_USE_BOTH);

        foreach ($checklist->items as $item) {
            $item->update([
                'title' => strtr($item->title, $replace),
                'reference' => $item->reference !== null ? strtr($item->reference, $replace) : null,
            ]);
        }

        $checklist->update(['username' => $data['username'] ?: null, 'email' => $data['email'] ?: null]);

        $this->dispatch('hinweis', text: __('Gespeichert.'));
    }

    public function toggle(int $itemId): void
    {
        Gate::authorize('checklist_manage');

        $checklist = $this->checklist();
        $item = $checklist->items()->findOrFail($itemId);

        if ($item->awaitsApproval()) {
            $this->dispatch('hinweis', text: __('Dieser Punkt wartet noch auf die Freigabe.'));

            return;
        }

        $item->update($item->isDone()
            ? ['done_at' => null, 'done_by' => null]
            : ['done_at' => now(), 'done_by' => auth()->id()]);

        $checklist->refreshCompletion();
    }

    public function approve(int $itemId): void
    {
        Gate::authorize('checklist_approve');

        $item = $this->checklist()->items()->where('needs_approval', true)->findOrFail($itemId);
        $item->update(['approved_at' => now(), 'approved_by' => auth()->id()]);

        $this->dispatch('hinweis', text: __('Freigegeben.'));
    }

    private function checklist(): Checklist
    {
        $this->nurEigenerKunde($this->customerId);

        return Checklist::where('customer_id', $this->customerId)->findOrFail($this->checklistId);
    }

    public function render()
    {
        $checklist = $this->checklist()->load(['items.doneBy', 'items.approvedBy', 'requester']);
        $request = $checklist->request ?? [];
        $options = $checklist->customer->checklistOptions()->ordered()->get()->keyBy('id');
        $shares = NetworkShare::withTrashed()->where('customer_id', $this->customerId)
            ->whereIn('id', array_keys($request['drives'] ?? []))->get()->keyBy('id');

        return view('livewire.checklist-progress', [
            'checklist' => $checklist,
            'sections' => $checklist->items->groupBy('section'),
            'requestedDrives' => collect($request['drives'] ?? [])
                ->map(fn ($level, $id) => ($share = $shares->get((int) $id)) ? ['share' => $share, 'level' => $level] : null)
                ->filter()->values(),
            'requestedOptions' => collect($request['options'] ?? [])->map(fn ($id) => $options->get((int) $id))->filter()->values(),
            'scriptPlan' => ChecklistAdScript::plan($checklist),
            'fixedPassword' => ChecklistSetting::for($checklist->customer)->isFixed(),
            'canManage' => Gate::allows('checklist_manage'),
            'canApprove' => Gate::allows('checklist_approve'),
        ]);
    }
}
