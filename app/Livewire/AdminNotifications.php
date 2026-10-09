<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\ExpiryNotice;
use App\Support\ExpiringItems;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Expiry mails, set up once for the whole installation.
 *
 * Two questions: what the mail reports (for everyone the same), and who
 * gets it. Recipients include users bound to one customer - they only ever
 * see that customer, ExpiringItems takes care of that. Every user can also
 * switch the mail on or off in their own profile; this page is the place
 * to do it for all of them at once.
 */
class AdminNotifications extends Component
{
    use WithPagination;

    /** @var array<int, string> */
    public array $kinds = [];

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('admin_setting');

        $this->kinds = Setting::expiryMailKinds();
    }

    public function updatedKinds(): void
    {
        Gate::authorize('admin_setting');

        $this->validate([
            'kinds' => ['required', 'array', 'min:1'],
            'kinds.*' => [Rule::in(Setting::EXPIRY_KINDS)],
        ], [], ['kinds' => __('Ablaufende Einträge')]);

        // In the fixed order, not in the order of clicking.
        $this->kinds = array_values(array_intersect(Setting::EXPIRY_KINDS, $this->kinds));
        Setting::setzen(Setting::EXPIRY_MAIL_KINDS, implode(',', $this->kinds));

        $this->dispatch('hinweis', text: __('Gespeichert.'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggle(int $id): void
    {
        Gate::authorize('admin_setting');

        $user = User::findOrFail($id);
        $user->update(['expiry_mail' => ! $user->expiry_mail]);

        $this->dispatch('hinweis', text: $user->expiry_mail
            ? __(':name bekommt ablaufende Einträge per Mail.', ['name' => $user->name])
            : __(':name bekommt keine Mail mehr zu ablaufenden Einträgen.', ['name' => $user->name]));
    }

    /**
     * The current list, to oneself - to see what a recipient gets. Records
     * nothing: the real run still mails every item once.
     */
    public function sendPreview(): void
    {
        Gate::authorize('admin_setting');

        $items = ExpiringItems::forUser(auth()->user(), $this->kinds);

        if ($items->isEmpty()) {
            $this->dispatch('hinweis', text: __('Derzeit läuft nichts ab – es gäbe nichts zu senden.'));

            return;
        }

        try {
            auth()->user()->notify((new ExpiryNotice($items))->locale(app()->getLocale()));
        } catch (Throwable $e) {
            report($e);
            $this->addError('preview', __('Die Mail ging nicht hinaus: :fehler', ['fehler' => $e->getMessage()]));

            return;
        }

        $this->dispatch('hinweis', text: __('Vorschau an :mail gesendet.', ['mail' => auth()->user()->email]));
    }

    public function render()
    {
        $users = User::with('customer')
            ->when($this->search !== '', fn ($q) => $q->whereEnthaelt(['name', 'username', 'email'], $this->search))
            ->orderByDesc('expiry_mail')->orderBy('name')
            ->paginate(Setting::seiteAdmin());

        return view('livewire.admin-notifications', [
            'users' => $users,
            'recipients' => User::where('expiry_mail', true)->count(),
        ])->layout('layouts.admin.app');
    }
}
