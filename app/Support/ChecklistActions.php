<?php

namespace App\Support;

use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * What gets done on a running checklist: ticking off and approving.
 *
 * Written once, called twice - by the Livewire component in the browser and
 * by the Mobile\ChecklistController. The rule that an item must not be ticked
 * off before approval belongs in one place.
 */
class ChecklistActions
{
    /**
     * Tick an item off or take the tick away again.
     *
     * @return string|null The reason if nothing happened - null otherwise.
     */
    public static function toggle(ChecklistItem $punkt, User $nutzer): ?string
    {
        Gate::forUser($nutzer)->authorize('checklist_manage');

        if ($punkt->awaitsApproval()) {
            return __('Dieser Punkt wartet noch auf die Freigabe.');
        }

        $punkt->update($punkt->isDone()
            ? ['done_at' => null, 'done_by' => null]
            : ['done_at' => now(), 'done_by' => $nutzer->id]);

        // If everything is ticked the list counts as done - and the other way round.
        $punkt->checklist->refreshCompletion();

        return null;
    }

    /**
     * Approve an item that the customer's management has to approve.
     *
     * Approving is not ticking off: afterwards IT may carry the item out.
     */
    public static function release(ChecklistItem $punkt, User $nutzer): void
    {
        Gate::forUser($nutzer)->authorize('checklist_approve');

        abort_unless($punkt->needs_approval, 404);

        $punkt->update(['approved_at' => now(), 'approved_by' => $nutzer->id]);
    }
}
