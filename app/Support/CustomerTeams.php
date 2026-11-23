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
 * Existing assignments keep their level; a new one starts with full access.
 * The change is logged on the customer.
 */
class CustomerTeams
{
    /** @param  list<int>  $teamIds */
    public static function sync(Customer $customer, array $teamIds, User $editor): void
    {
        $teamIds = array_values(array_unique(array_map('intval', $teamIds)));
        $mine = $editor->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();

        if ($teamIds !== [] && array_intersect($teamIds, $mine) === []) {
            throw ValidationException::withMessages([
                'teams' => __('Danach wärst du selbst ausgesperrt. Wähle mindestens ein Team, in dem du Mitglied bist.'),
            ]);
        }

        $before = $customer->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();

        $customer->teams()->sync(collect($teamIds)->mapWithKeys(fn ($id) => [
            $id => in_array($id, $before, true) ? [] : ['level' => CustomerAccess::FULL],
        ])->all());

        sort($before);
        $after = $teamIds;
        sort($after);
        if ($before === $after) {
            return;
        }

        $names = fn (array $ids) => Team::whereIn('id', $ids)->orderBy('name')->pluck('name')->join(', ') ?: __('alle Benutzer');

        activity()
            ->event('access_changed')
            ->performedOn($customer)
            ->causedBy($editor)
            ->withProperties([
                'objekt' => $customer->name,
                'old' => [__('Teams') => $names($before)],
                'attributes' => [__('Teams') => $names($after)],
            ])
            ->log('Zugriff geändert');
    }
}
