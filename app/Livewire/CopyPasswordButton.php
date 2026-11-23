<?php

namespace App\Livewire;

use App\Exceptions\TooManyPasswordReveals;
use App\Models\CredentialLink;
use App\Support\CustomerAccess;
use App\Support\PasswordReveal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Copy password" in the head line of a card (design 1A): the most frequent
 * grip on a device without opening the card.
 *
 * One password linked: one click copies it. Several: a small menu names
 * them (note or login name). None, or no right to see passwords: nothing.
 *
 * Same path as the password field in the card (PasswordField): the value is
 * fetched through PasswordReveal - right, throttle, log entry - and goes to
 * the clipboard once, never into the page. The offered link ids are
 * #[Locked]: the browser cannot swap one for a link of another device.
 */
class CopyPasswordButton extends Component
{
    /** @var list<array{id: int, label: string, user: ?string}> */
    #[Locked]
    public array $links = [];

    public function mount(Model $device): void
    {
        // Level "read without passwords" (CustomerAccess): no button at all.
        if (! method_exists($device, 'zugangsdaten') || ! Gate::allows('logingeneral_viewAny')
            || ! CustomerAccess::canRevealSecrets(auth()->user(), (int) $device->customer_id)) {
            return;
        }

        $this->links = $device->zugangsdaten()
            ->filter(fn ($link) => ! $link->login->istSchluessel() && filled($link->login->password))
            // The user name tells two entries apart in the menu ("Administrator"
            // twice, once local, once domain) - shown in the card anyway.
            ->map(fn ($link) => [
                'id' => (int) $link->id,
                'label' => (string) ($link->note ?: $link->login->name),
                'user' => $link->login->username,
            ])
            ->values()
            ->all();
    }

    public function copy(int $linkId): void
    {
        if (! in_array($linkId, array_column($this->links, 'id'), true)) {
            return;
        }

        $link = CredentialLink::find($linkId);

        if (! $link) {
            return;
        }

        try {
            $value = app(PasswordReveal::class)->ausVerknuepfung($link, auth()->user());
        } catch (TooManyPasswordReveals $throttled) {
            $this->addError('kennwort', $throttled->getMessage());

            return;
        }

        if ($value !== null) {
            $this->dispatch('password-ready', value: $value)->self();
        }
    }

    public function render()
    {
        return view('livewire.copy-password-button');
    }
}
