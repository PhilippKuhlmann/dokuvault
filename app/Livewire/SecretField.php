<?php

namespace App\Livewire;

use App\Exceptions\TooManyPasswordReveals;
use App\Support\CustomerAccess;
use App\Support\PasswordReveal;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A secret on an object - covered until somebody asks for it.
 *
 * The counterpart to PasswordField, but for values that hang directly on the
 * device (BMC password, DSRM password, encryption key ...) instead of on a
 * credential link. They used to stand as plain text in the delivered HTML of
 * every list and every card - the eye was only JavaScript.
 *
 * Now the field carries only model, id and field name. Only the click fetches
 * the value through the server, with the same permission as the list
 * (viewAny), a log entry and the same throttle as PasswordField.
 *
 * Model, id and field are #[Locked] and are checked on the server against the
 * secret columns and the policy - through this field only comes out what
 * stood on the page anyway.
 */
class SecretField extends Component
{
    #[Locked]
    public string $modell;

    #[Locked]
    public int $id;

    #[Locked]
    public string $feld;

    public string $width = 'w-full';

    public bool $offen = false;

    /**
     * Whether the user's team may see passwords of this customer at all
     * (CustomerAccess, level "read without passwords"). Without, the field
     * stays covered and has neither eye nor copy - PasswordReveal refuses
     * anyway, this only spares the button that would fail.
     */
    #[Locked]
    public bool $mayReveal = true;

    public ?string $wert = null;

    public function mount(string $modell, int $id, string $feld, string $width = 'w-full'): void
    {
        $this->modell = $modell;
        $this->id = $id;
        $this->feld = $feld;
        $this->width = $width;
        // The page's customer when known (no query per field in a list),
        // else the one of the row.
        $customerId = ! CustomerAccess::anyAssigned() ? null
            : (CustomerAccess::currentCustomerId()
                ?? (is_subclass_of($modell, Model::class) ? $modell::query()->whereKey($id)->value('customer_id') : null));
        $this->mayReveal = CustomerAccess::canRevealSecrets(auth()->user(), $customerId ? (int) $customerId : null);
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
     * Copies the secret without revealing it: the value goes to the browser
     * once (into the clipboard) and stands nowhere in the DOM afterwards.
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
     * Fetches the plain text - with a field allowlist, a permission check, a
     * throttle and a log entry, see App\Support\PasswordReveal. The shared core
     * of show() and copy().
     */
    private function fetchValue(): ?string
    {
        try {
            return app(PasswordReveal::class)->fromField($this->modell, $this->id, $this->feld, auth()->user());
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
        // The same view as PasswordField - the same covered field with an eye
        // and a copy button.
        return view('livewire.password-field');
    }
}
