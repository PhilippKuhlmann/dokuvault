<?php

namespace App\Livewire;

use Livewire\Component;

/**
 * The user's own switch for the expiry mail, in the profile.
 *
 * Also for users bound to one customer - they get that customer's entries
 * only. An admin can set the same switch for everyone under
 * Einstellungen -> Benachrichtigungen; whoever touched it last wins.
 */
class NotificationProfile extends Component
{
    public bool $enabled = false;

    public function mount(): void
    {
        $this->enabled = (bool) auth()->user()->expiry_mail;
    }

    public function updatedEnabled(): void
    {
        auth()->user()->update(['expiry_mail' => $this->enabled]);

        $this->dispatch('hinweis', text: $this->enabled
            ? __('Sie bekommen ablaufende Einträge per Mail.')
            : __('Sie bekommen keine Mail mehr zu ablaufenden Einträgen.'));
    }

    public function render()
    {
        return view('livewire.notification-profile');
    }
}
