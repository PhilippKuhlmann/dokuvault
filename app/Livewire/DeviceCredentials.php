<?php

namespace App\Livewire;

use App\Livewire\Concerns\ScopedToCustomer;
use App\Livewire\Concerns\ValidatesOnInput;
use App\Models\Customer;
use App\Models\LoginGeneral;
use App\Support\CredentialLinking;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Links a device with logins from "Logins Allgemein".
 *
 * Deliberately no password fields of its own on the device: a password often
 * applies to several systems. It stands once in the login entry and is only
 * attached here - when changing it one place has to change, not five.
 */
class DeviceCredentials extends Component
{
    use ScopedToCustomer;
    use ValidatesOnInput;

    // Scalars instead of a model instance: robust with polymorphic models and Livewire hydration.
    #[Locked]
    public string $modelClass;

    #[Locked]
    public int $modelId;

    #[Locked]
    public int $customerId;

    // Vorhandenes Login anhängen
    public $login_id = '';

    public string $note = '';

    // Create a new login and attach it right away
    public bool $neu = false;

    public string $name = '';

    public string $username = '';

    public string $password = '';

    // Embedded: without a card frame of its own, because the block then sits in the card
    // des Formulars steht (x-create.main, Slot "nach").
    #[Locked]
    public bool $eingebettet = false;

    /** In the modal the frame brings the padding - then none from the block. */
    public bool $randlos = false;

    /**
     * The rules of both forms together.
     *
     * The block has two: one attaches existing credentials, one creates new
     * ones. Together they are right, because while typing only the one changed
     * field is checked - whoever types a name triggers no check of login_id.
     * no check of login_id.
     */
    protected function regeln(): array
    {
        $verknuepfung = app(CredentialLinking::class);

        return [
            ...$verknuepfung->attachRules($this->customerId, auth()->user()),
            ...$verknuepfung->createRules(),
        ];
    }

    protected function fieldNames(): array
    {
        return app(CredentialLinking::class)->attributeNames();
    }

    public function mount($model, $customer, bool $eingebettet = false, bool $randlos = false): void
    {
        // On mounting, not only on the action: a block that can be built at
        // all with a foreign device has already said too much. The checks in
        // the actions stay - they catch the case that someone swaps the number
        // later.
        $this->onlyOwnCustomer($customer->id);
        abort_if($model->customer_id !== $customer->id, 403);

        $this->modelClass = $model::class;
        $this->modelId = $model->id;
        $this->customerId = $customer->id;
        $this->eingebettet = $eingebettet;
        $this->randlos = $randlos;
    }

    /**
     * Authorisation: permission to edit the device kind AND customer membership.
     * (Public properties can be manipulated on the client -> check on every action.)
     */
    /**
     * Welche Arten von Zugangsdaten dieser Benutzer sehen darf.
     *
     * Passwords and SSH keys sit in the same table but have separate
     * permissions. Without that distinction anyone with the login permission
     * would see the keys through the device block as well.
     *
     * @return array<int, string>
     */
    protected function visibleTypes(): array
    {
        return app(CredentialLinking::class)->visibleKinds(auth()->user());
    }

    protected function device()
    {
        $device = $this->modelClass::findOrFail($this->modelId);

        Gate::authorize(strtolower(class_basename($this->modelClass)).'_update');

        // One of the two kinds is enough - which, is decided by visibleTypes().
        abort_if($this->visibleTypes() === [], 403);

        $user = auth()->user();
        abort_if($user->customer_id && $user->customer_id !== $device->customer_id, 403);
        abort_if($device->customer_id !== $this->customerId, 403);

        return $device;
    }

    public function attach(): void
    {
        $device = $this->device();

        $this->enableCheck();

        $validated = $this->validate(
            Arr::only($this->regeln(), ['login_id', 'note']), [], $this->fieldNames()
        );

        $this->verknuepfen($device, (int) $validated['login_id'], $validated['note']);
        // A list around this block shows the addresses or credentials in its
        // columns - without this message it would stay on the old state.
        $this->dispatch('device-changed');
    }

    public function create(): void
    {
        $device = $this->device();
        Gate::authorize('logingeneral_create');

        $this->enableCheck();

        $validated = $this->validate(
            Arr::only($this->regeln(), ['name', 'username', 'password', 'note']), [], $this->fieldNames()
        );

        app(CredentialLinking::class)->create($device, $validated);
        $this->reset('login_id', 'note');
        $this->reset('name', 'username', 'password', 'neu');
        // A list around this block shows the addresses or credentials in its
        // columns - without this message it would stay on the old state.
        $this->dispatch('device-changed');
    }

    /** Skip duplicate links silently - the unique index would throw a 500. */
    protected function verknuepfen($device, int $loginId, ?string $note): void
    {
        app(CredentialLinking::class)->attach($device, $loginId, $note);

        $this->reset('login_id', 'note');
    }

    /** Only releases the link - the login entry itself stays. */
    public function detach(int $id): void
    {
        $device = $this->device();

        app(CredentialLinking::class)->detach($device, $id, auth()->user());
        // A list around this block shows the addresses or credentials in its
        // columns - without this message it would stay on the old state.
        $this->dispatch('device-changed');
    }

    public function render()
    {
        $device = $this->modelClass::find($this->modelId);
        $arten = $this->visibleTypes();
        $entries = ($device ? $device->zugangsdaten() : collect())
            ->filter(fn ($link) => in_array($link->login->kind, $arten, true))
            ->values();

        return view('livewire.device-credentials', [
            'entries' => $entries,
            // As a model, not as an id: the customer route binds through the slug.
            'kunde' => Customer::find($this->customerId),
            // Logins already linked drop out of the select - offering one a
            // second time would be a dead end.
            // The keys as well: an SSH key belongs on a device just as much as
            // a password - otherwise it could be attached nowhere. Only this
            // filter falls away, the trash stays.
            // Grouped by kind: in a mixed list one cannot tell from a name
            // whether a password or a key is behind it.
            'logins' => LoginGeneral::withoutGlobalScope(LoginGeneral::SCOPE)
                ->where('customer_id', $this->customerId)
                ->whereIn('kind', $arten)
                ->whereNotIn('id', $entries->pluck('login_general_id'))
                ->orderBy('name')->get()
                ->groupBy(fn ($login) => $login->istSchluessel() ? 'sshkey' : 'password'),
        ]);
    }
}
