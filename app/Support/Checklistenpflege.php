<?php

namespace App\Support;

use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Was an einer laufenden Checkliste getan wird: abhaken und freigeben.
 *
 * Einmal geschrieben, zweimal gerufen - von der Livewire-Komponente im
 * Browser und vom Mobile\ChecklistController. Die Regel, dass ein Punkt vor
 * der Freigabe nicht abgehakt werden darf, gehoert an eine Stelle.
 */
class Checklistenpflege
{
    /**
     * Einen Punkt abhaken oder den Haken wieder wegnehmen.
     *
     * @return string|null Der Grund, wenn nichts geschah - sonst null.
     */
    public static function umschalten(ChecklistItem $punkt, User $nutzer): ?string
    {
        Gate::forUser($nutzer)->authorize('checklist_manage');

        if ($punkt->awaitsApproval()) {
            return __('Dieser Punkt wartet noch auf die Freigabe.');
        }

        $punkt->update($punkt->isDone()
            ? ['done_at' => null, 'done_by' => null]
            : ['done_at' => now(), 'done_by' => $nutzer->id]);

        // Ist alles abgehakt, gilt die Liste als erledigt - und umgekehrt.
        $punkt->checklist->refreshCompletion();

        return null;
    }

    /**
     * Einen Punkt freigeben, den die Leitung des Kunden freigeben muss.
     *
     * Freigeben ist kein Abhaken: Danach darf die IT den Punkt erledigen.
     */
    public static function freigeben(ChecklistItem $punkt, User $nutzer): void
    {
        Gate::forUser($nutzer)->authorize('checklist_approve');

        abort_unless($punkt->needs_approval, 404);

        $punkt->update(['approved_at' => now(), 'approved_by' => $nutzer->id]);
    }
}
