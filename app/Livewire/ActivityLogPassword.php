<?php

namespace App\Livewire;

use App\Exceptions\TooManyPasswordReveals;
use App\Models\PasswordHistory;
use App\Support\PasswordReveal;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The previous password in a log row - on click.
 *
 * The value is not in the activity entry: that stays forever and lists every
 * customer on one page. It comes from the password history and disappears
 * with its deadline. For the viewer it is the same move as with any other
 * change.
 */
class ActivityLogPassword extends Component
{
    /** Ids of the history entries belonging to this row. */
    #[Locked]
    public array $ids = [];

    /** Labels of the fields, so it is clear which password is meant. */
    #[Locked]
    public array $felder = [];

    public bool $offen = false;

    /** Loaded values - filled only after the click. */
    public array $werte = [];

    public function show(): void
    {
        // The same permission as the page the component sits on. It is checked
        // here all the same - the call comes from the browser.
        Gate::authorize('admin_activity');

        // Every value through Kennwortabruf: brake, log entry, customer and
        // the right to see that kind of object - like every other reveal.
        try {
            $this->werte = PasswordHistory::whereIn('id', $this->ids)
                ->get()
                ->map(fn ($eintrag) => [
                    'feld' => config('custom.secret_field_labels')[$eintrag->field] ?? $eintrag->field,
                    'wert' => app(PasswordReveal::class)->fromHistory($eintrag, auth()->user()),
                ])
                ->filter(fn ($eintrag) => $eintrag['wert'] !== null)
                ->values()
                ->all();
        } catch (TooManyPasswordReveals $bremse) {
            $this->addError('kennwort', $bremse->getMessage());

            return;
        }

        $this->offen = true;
    }

    public function hide(): void
    {
        $this->werte = [];
        $this->offen = false;
    }

    public function render()
    {
        return view('livewire.activity-log-password');
    }
}
