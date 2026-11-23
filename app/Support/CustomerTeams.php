<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Assigns teams to a customer (Admin -> Kunden, section "Zugriff").
 *
 * Whoever saves must keep the customer for themselves: with teams chosen,
 * at least one of them has to be theirs. Otherwise one click would make the
 * customer vanish for the very person who just set it up - and only another
 * member could bring it back.
 *
 * Each team gets a level (CustomerAccess::LEVELS). Not sent: an existing
 * assignment keeps its level, a new one starts with full access. The change
 * is logged on the customer, with the levels.
 */
class CustomerTeams
{
    /**
     * @param  list<int>  $teamIds
     * @param  array<int|string, string>  $levels  team id => level; missing: kept, or full for a new one
     */
    public static function sync(Customer $customer, array $teamIds, User $editor, array $levels = []): void
    {
        // Only with full access may the access be changed: a member who may
        // only read would otherwise lift their own team to full.
        CustomerAccess::assertWritable($customer->id);

        $teamIds = array_values(array_unique(array_map('intval', $teamIds)));
        $existing = $customer->teams()->get()->mapWithKeys(fn ($team) => [(int) $team->id => $team->pivot->level])->all();

        $chosen = collect($teamIds)->mapWithKeys(function ($id) use ($levels, $existing) {
            $level = $levels[$id] ?? $existing[$id] ?? CustomerAccess::FULL;

            return [$id => in_array($level, CustomerAccess::LEVELS, true) ? $level : CustomerAccess::FULL];
        })->all();

        // The editor keeps full access through a team of their own - with
        // less, they could not even undo this choice.
        $mine = $editor->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();
        $keepsFull = collect($chosen)->contains(fn ($level, $id) => $level === CustomerAccess::FULL && in_array($id, $mine, true));

        if ($chosen !== [] && ! $keepsFull) {
            throw ValidationException::withMessages([
                'teams' => __('Danach wärst du selbst ausgesperrt. Mindestens ein Team, in dem du Mitglied bist, braucht Vollzugriff.'),
            ]);
        }

        $customer->teams()->sync(collect($chosen)->map(fn ($level) => ['level' => $level])->all());
        CustomerAccess::flush();

        ksort($existing);
        ksort($chosen);
        if ($existing === $chosen) {
            return;
        }

        $describe = fn (array $assigned) => $assigned === []
            ? __('alle Benutzer')
            : Team::whereIn('id', array_keys($assigned))->orderBy('name')->get()
                ->map(fn ($team) => $team->name.' ('.CustomerAccess::label($assigned[$team->id]).')')
                ->join(', ');

        activity()
            ->event('access_changed')
            ->performedOn($customer)
            ->causedBy($editor)
            ->withProperties([
                'objekt' => $customer->name,
                'old' => [__('Teams') => $describe($existing)],
                'attributes' => [__('Teams') => $describe($chosen)],
            ])
            ->log('Zugriff geändert');
    }
}
