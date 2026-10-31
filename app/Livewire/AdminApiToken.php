<?php

namespace App\Livewire;

use App\Livewire\Concerns\ValidatesOnInput;
use App\Support\LocalTime;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Create and revoke API tokens.
 *
 * /admin/apitoken used to be a route returning raw JSON - and creating
 * another token named "optin" on every call. A menu item pointing at it would
 * have generated a token on every click, without anyone ever getting rid of
 * the old ones.
 *
 * Shown are the tokens of the signed-in user: a token speaks with their
 * permissions, and managing someone else's tokens would be a different
 */
class AdminApiToken extends Component
{
    use ValidatesOnInput;

    /** Name of the new token - what it is for. */
    public string $name = '';

    /**
     * Ablaufdatum des neuen Tokens - Pflicht.
     *
     * A token without a deadline is permanent access with the permissions of
     * its user, lying in the script that uses it. Prefilled from the
     * configuration, changeable.
     */
    public string $expiresAt = '';

    /**
     * The plain text, exactly once.
     *
     * Only the hash is stored; whoever does not take it now has to create a
     * new one. Hence it stands large on the page and not in a message that
     * disappears after three seconds.
     */
    public ?string $frischerToken = null;

    /** One source for creating and for the check while typing. */
    protected function regeln(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // Required and in the future: no unlimited token.
            'expiresAt' => ['required', 'date', 'after:today'],
        ];
    }

    protected function fieldNames(): array
    {
        return ['name' => __('Bezeichnung'), 'expiresAt' => __('Läuft ab am')];
    }

    public function mount(): void
    {
        Gate::authorize('admin_apitoken');

        $this->expiresAt = $this->defaultDeadline();
    }

    public function anlegen(): void
    {
        Gate::authorize('admin_apitoken');

        $this->enableCheck();

        $this->validate($this->regeln(), [], $this->fieldNames());

        // The third parameter of createToken is the expiry - Sanctum rejects an
        // expired token by itself afterwards.
        $this->frischerToken = auth()->user()
            ->createToken($this->name, ['*'], LocalTime::endOfDay($this->expiresAt))
            ->plainTextToken;

        $this->name = '';
        $this->expiresAt = $this->defaultDeadline();
    }

    /** The prefilled deadline: today plus the default from the configuration. */
    private function defaultDeadline(): string
    {
        return now()->addDays(config('custom.api_token.gueltigkeit_tage_standard'))->format('Y-m-d');
    }

    public function widerrufen(int $id): void
    {
        Gate::authorize('admin_apitoken');

        // Through the relation, not through the id alone: otherwise another
        // user's token could be revoked with a foreign id.
        auth()->user()->tokens()->whereKey($id)->delete();

        $this->dispatch('notice', text: __('Token widerrufen.'));
    }

    public function hide(): void
    {
        $this->frischerToken = null;
    }

    public function render()
    {
        return view('livewire.admin-api-token', [
            'tokens' => auth()->user()->tokens()->latest('id')->get(),
        ])->layout('layouts.admin.app');
    }
}
