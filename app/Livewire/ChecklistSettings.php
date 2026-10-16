<?php

namespace App\Livewire;

use App\Livewire\Concerns\GehoertZumKunden;
use App\Models\ADGroup;
use App\Models\ChecklistOption;
use App\Models\ChecklistSetting;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use App\Models\Customer;
use App\Models\NetworkShare;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Per-customer setup of the checklists: which documented network drives the
 * onboarding form offers, its programs, and the template steps - standard steps can
 * be switched off, own steps added.
 */
class ChecklistSettings extends Component
{
    use GehoertZumKunden;

    public int $customerId;

    public ?int $editingId = null;

    public string $kind = 'app';

    public string $name = '';

    /** Id of a documented AD group of this customer, '' for none. */
    public string $ad_group_id = '';

    public string $detail = '';

    public string $assignee = 'it';

    public bool $needs_approval = false;

    /** Start password of new AD users: 'generated' by rule or 'fixed'. */
    public string $passwordMode = 'generated';

    public int|string $passwordLength = 8;

    public int|string $passwordUppercase = 1;

    public int|string $passwordSpecialCount = 1;

    public int|string $passwordDigits = 2;

    public string $passwordSpecials = '!-';

    /** Never loaded into the form - empty means "keep the stored one". */
    public string $fixedPassword = '';

    public bool $forceChangeDefault = false;

    /** @var array<string, array{section: string, title: string, assignee: string, due_offset: int|string}> */
    public array $steps = [];

    public function mount(Customer $customer): void
    {
        $this->nurEigenerKunde($customer->id);
        Gate::authorize('checklist_manage');

        $this->customerId = $customer->id;
        $this->resetSteps();

        $setting = ChecklistSetting::for($customer);
        $this->passwordMode = $setting->password_mode;
        $this->passwordLength = $setting->password_length;
        $this->passwordUppercase = $setting->password_uppercase;
        $this->passwordSpecialCount = $setting->password_special_count;
        $this->passwordDigits = $setting->password_digits;
        $this->passwordSpecials = $setting->password_specials;
        $this->forceChangeDefault = $setting->force_change_default;
    }

    public function savePasswordRule(): void
    {
        $customer = $this->customer();
        $setting = ChecklistSetting::for($customer);

        $data = $this->validate([
            'passwordMode' => ['required', Rule::in(['generated', 'fixed'])],
            'passwordLength' => ['required', 'integer', 'between:6,32'],
            'passwordUppercase' => ['required', 'integer', 'between:1,4'],
            'passwordSpecialCount' => ['required', 'integer', 'between:1,4'],
            'passwordDigits' => ['required', 'integer', 'between:0,8'],
            'passwordSpecials' => ['required', 'string', 'max:20', 'regex:/^[^A-Za-z0-9\s]+$/'],
            'fixedPassword' => [$this->passwordMode === 'fixed' && ! $setting->fixed_password ? 'required' : 'nullable', 'string', 'min:4', 'max:128'],
            'forceChangeDefault' => ['boolean'],
        ], [], [
            'passwordLength' => __('Länge'),
            'passwordUppercase' => __('Großbuchstaben'),
            'passwordSpecialCount' => __('Anzahl Sonderzeichen'),
            'passwordDigits' => __('Anzahl Ziffern'),
            'passwordSpecials' => __('Erlaubte Sonderzeichen'),
            'fixedPassword' => __('Festes Start-Kennwort'),
        ]);

        if (! ChecklistSetting::fits((int) $data['passwordLength'], (int) $data['passwordUppercase'], (int) $data['passwordSpecialCount'], (int) $data['passwordDigits'])) {
            $this->addError('passwordLength', __('Zu kurz für :upper Großbuchstaben, :special Sonderzeichen und :digits Ziffern.', [
                'upper' => $data['passwordUppercase'], 'special' => $data['passwordSpecialCount'], 'digits' => $data['passwordDigits'],
            ]));

            return;
        }

        $setting->fill([
            'password_mode' => $data['passwordMode'],
            'password_length' => (int) $data['passwordLength'],
            'password_uppercase' => (int) $data['passwordUppercase'],
            'password_special_count' => (int) $data['passwordSpecialCount'],
            'password_digits' => (int) $data['passwordDigits'],
            'password_specials' => count_chars($data['passwordSpecials'], 3),
            'force_change_default' => $data['forceChangeDefault'],
        ]);
        if (filled($data['fixedPassword'])) {
            $setting->fixed_password = $data['fixedPassword'];
        }
        $setting->save();

        $this->passwordSpecials = $setting->password_specials;
        $this->fixedPassword = '';
        $this->dispatch('hinweis', text: __('Kennwort-Regel gespeichert.'));
    }

    private function customer(): Customer
    {
        Gate::authorize('checklist_manage');
        $this->nurEigenerKunde($this->customerId);

        return Customer::findOrFail($this->customerId);
    }

    private function resetSteps(): void
    {
        foreach (['onboarding', 'offboarding'] as $type) {
            $this->steps[$type] = ['section' => '', 'title' => '', 'assignee' => 'it', 'due_offset' => 0];
        }
    }

    public function saveOption(): void
    {
        $customer = $this->customer();

        $data = $this->validate([
            'kind' => ['required', Rule::in(ChecklistOption::KINDS)],
            'name' => ['required', 'string', 'max:255'],
            'ad_group_id' => ['nullable', Rule::exists('ad_groups', 'id')->where('customer_id', $customer->id)->whereNull('deleted_at')],
            'detail' => ['nullable', 'string', 'max:255'],
            'assignee' => ['required', Rule::in(['it', 'customer'])],
            'needs_approval' => ['boolean'],
        ], [], ['name' => __('Name')]);

        $data['ad_group_id'] = $data['ad_group_id'] ?: null;
        $data['detail'] = $data['detail'] ?: null;

        if ($this->editingId) {
            $customer->checklistOptions()->findOrFail($this->editingId)->update($data);
            $this->dispatch('hinweis', text: __('Gespeichert.'));
        } else {
            $data['sort'] = (int) $customer->checklistOptions()->where('kind', $data['kind'])->max('sort') + 1;
            $customer->checklistOptions()->create($data);
            $this->dispatch('hinweis', text: __('Hinzugefügt.'));
        }

        $this->cancelEdit();
    }

    public function editOption(int $id): void
    {
        $option = $this->customer()->checklistOptions()->findOrFail($id);

        $this->editingId = $option->id;
        $this->kind = $option->kind;
        $this->name = $option->name;
        $this->ad_group_id = (string) ($option->ad_group_id ?? '');
        $this->detail = $option->detail ?? '';
        $this->assignee = $option->assignee;
        $this->needs_approval = $option->needs_approval;
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $kind = $this->kind;
        $this->reset('editingId', 'name', 'ad_group_id', 'detail', 'assignee', 'needs_approval');
        $this->kind = $kind;
        $this->resetErrorBag();
    }

    public function toggleActive(int $id): void
    {
        $option = $this->customer()->checklistOptions()->findOrFail($id);
        $option->update(['active' => ! $option->active]);
    }

    public function deleteOption(int $id): void
    {
        $this->customer()->checklistOptions()->findOrFail($id)->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
    }

    /** Offers a documented network drive in the onboarding form - or stops offering it. */
    public function toggleShare(int $id): void
    {
        $share = NetworkShare::where('customer_id', $this->customer()->id)->findOrFail($id);
        $share->update(['in_onboarding_form' => ! $share->in_onboarding_form]);
    }

    /** Switches a standard step off for this customer - or back on. */
    public function toggleStandard(int $itemId): void
    {
        $customer = $this->customer();
        $item = ChecklistTemplateItem::whereNull('customer_id')->findOrFail($itemId);

        $customer->hiddenChecklistItems()->toggle([$item->id]);
    }

    public function addStep(string $type): void
    {
        $customer = $this->customer();
        abort_unless(in_array($type, ['onboarding', 'offboarding'], true), 404);

        $data = $this->validate([
            "steps.$type.section" => ['required', 'string', 'max:100'],
            "steps.$type.title" => ['required', 'string', 'max:255'],
            "steps.$type.assignee" => ['required', Rule::in(['it', 'customer'])],
            "steps.$type.due_offset" => ['required', 'integer', 'between:-60,365'],
        ], [], [
            "steps.$type.section" => __('Abschnitt'),
            "steps.$type.title" => __('Schritt'),
            "steps.$type.due_offset" => __('Fällig'),
        ])['steps'][$type];

        $template = ChecklistTemplate::ofType($type);

        // Own steps are due on the key date (T0) - the settings ask no due date.
        // At the end of its section; a new section goes before "Erster Tag" / "Abschluss" (900s).
        $sort = $template->items()->where('section', $data['section'])
            ->where(fn ($query) => $query->whereNull('customer_id')->orWhere('customer_id', $customer->id))
            ->max('sort');

        $template->items()->create([
            'customer_id' => $customer->id,
            'section' => $data['section'],
            'title' => $data['title'],
            'assignee' => $data['assignee'],
            'due_offset' => (int) $data['due_offset'],
            'sort' => $sort !== null ? $sort + 1 : 700,
        ]);

        $this->steps[$type] = ['section' => $data['section'], 'title' => '', 'assignee' => 'it', 'due_offset' => 0];
        $this->dispatch('hinweis', text: __('Schritt hinzugefügt.'));
    }

    public function deleteStep(int $itemId): void
    {
        $customer = $this->customer();

        ChecklistTemplateItem::where('customer_id', $customer->id)->findOrFail($itemId)->delete();
    }

    public function render()
    {
        $customer = Customer::findOrFail($this->customerId);
        $hidden = $customer->hiddenChecklistItems()->pluck('checklist_template_items.id')->all();

        $templates = ChecklistTemplate::orderBy('id')->get()->map(fn ($template) => [
            'template' => $template,
            'items' => $template->items()
                ->where(fn ($query) => $query->whereNull('customer_id')->orWhere('customer_id', $customer->id))
                ->get(),
        ]);

        $setting = ChecklistSetting::for($customer);
        $preview = new ChecklistSetting([
            'password_length' => max(6, min(32, (int) $this->passwordLength)),
            'password_uppercase' => max(1, min(4, (int) $this->passwordUppercase)),
            'password_special_count' => max(1, min(4, (int) $this->passwordSpecialCount)),
            'password_digits' => max(0, min(8, (int) $this->passwordDigits)),
            'password_specials' => preg_replace('/[A-Za-z0-9\s]/', '', $this->passwordSpecials) ?: '!',
        ]);

        return view('livewire.checklist-settings', [
            'customer' => $customer,
            'hasFixedPassword' => filled($setting->fixed_password),
            'passwordExample' => ChecklistSetting::fits($preview->password_length, $preview->password_uppercase, $preview->password_special_count, $preview->password_digits) ? $preview->example() : null,
            'shares' => NetworkShare::where('customer_id', $customer->id)->with(['readGroup', 'writeGroup'])
                ->orderBy('drive_letter')->orderBy('name')->get(),
            'options' => $customer->checklistOptions()->whereIn('kind', ChecklistOption::KINDS)->ordered()->with('adGroup')->get()->groupBy('kind'),
            'adGroups' => ADGroup::where('customer_id', $customer->id)->orderBy('name')->pluck('name', 'id'),
            'templates' => $templates,
            'hidden' => $hidden,
        ]);
    }
}
