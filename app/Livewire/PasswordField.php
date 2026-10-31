<?php

namespace App\Livewire;

use App\Exceptions\TooManyPasswordReveals;
use App\Models\CredentialLink;
use App\Support\PasswordReveal;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A password in a device list - covered until somebody asks for it.
 *
 * The plain text used to stand in the delivered HTML: the eye was pure
 * JavaScript, the value lay in the DOM long before. Whoever opened the list
 * had every password, without a click and without it being recorded anywhere.
 *
 * Now the field carries the link only, not the value. Only the click fetches
 * it - through the server, with the same permission as the list, with an entry
 * in the log and a throttle against harvesting in bulk.
 *
 * The link id is #[Locked]: Livewire signs it, and the browser cannot swap it
 * for a foreign one. So through this field only comes out what stood on the
 * page anyway.
 */
class PasswordField extends Component
{
    #[Locked]
    public int $linkId;

    public string $width = 'w-full';

    public bool $offen = false;

    /** Filled only after the click - before that no value lies in the component. */
    public ?string $wert = null;

    public function mount(int $linkId, string $width = 'w-full'): void
    {
        $this->linkId = $linkId;
        $this->width = $width;
    }

    public function show(): void
    {
        $wert = $this->fetchValue();

        if ($wert === null) {
            return;
        }

        $this->wert = $wert;
        $this->offen = true;
    }

    /**
     * Copies the password without revealing it: the value goes to the browser
     * once (into the clipboard) and stands nowhere in the DOM afterwards. That
     * way it can be taken over without appearing on the screen.
     */
    public function kopieren(): void
    {
        $wert = $this->fetchValue();

        if ($wert === null) {
            return;
        }

        $this->dispatch('password-ready', wert: $wert)->self();
    }

    /**
     * Fetches the plain text - with a permission check, a throttle and a log
     * entry, see App\Support\PasswordReveal. The shared core of show() and copy().
     */
    private function fetchValue(): ?string
    {
        $link = CredentialLink::find($this->linkId);

        if (! $link) {
            return null;
        }

        try {
            return app(PasswordReveal::class)->ausVerknuepfung($link, auth()->user());
        } catch (TooManyPasswordReveals $bremse) {
            $this->addError('kennwort', $bremse->getMessage());

            return null;
        }
    }

    public function hide(): void
    {
        $this->wert = null;
        $this->offen = false;
    }

    public function render()
    {
        return view('livewire.password-field');
    }
}
