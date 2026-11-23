<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Which customers a staff user may see, by team.
 *
 * A customer without a team is open to every staff user - as before teams
 * existed. A customer with one or more teams (customer_team) is visible to
 * the members of those teams only; for everyone else it does not exist.
 * That holds for admins as well: whoever runs the installation for the
 * customers may still keep their own company to themselves.
 *
 * Not touched: customer logins (users.customer_id), whom OwnCustomerScope
 * keeps inside their own customer anyway, and calls without a user - agents,
 * queue, console. Whatever those hand on to a person (expiry mails) has to
 * ask canSee() for that person itself.
 *
 * The hidden customers are a subquery, not a list read once: a team
 * changed in the middle of a request (or a test) counts at once, and the
 * tables are small.
 */
class CustomerAccess
{
    public const FULL = 'full';

    public const READ = 'read';

    public const READ_NO_SECRETS = 'read_no_secrets';

    /** Strongest first - with two teams on one customer the higher level counts. */
    public const LEVELS = [self::FULL, self::READ, self::READ_NO_SECRETS];

    /**
     * The ids of the customers the user may not see, as a subquery for
     * whereNotIn - or null when nothing is hidden for them.
     */
    public static function hiddenIdsQuery(?User $user): ?Builder
    {
        if (! $user || $user->customer_id) {
            return null;
        }

        $own = DB::table('customer_team as mine')
            ->join('team_user', 'team_user.team_id', '=', 'mine.team_id')
            ->where('team_user.user_id', $user->getKey())
            ->select('mine.customer_id');

        return DB::table('customer_team as assigned')
            ->select('assigned.customer_id')
            ->whereNotIn('assigned.customer_id', $own);
    }

    /**
     * Leaves out the rows of hidden customers - for queries no global scope
     * reaches: the activity log, the users, raw statistics. Rows without a
     * customer stay. Works on Eloquent and query builders alike.
     *
     * @template T of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Relations\Relation
     *
     * @param  T  $query
     * @return T
     */
    public static function exceptHidden($query, string $column = 'customer_id', ?User $user = null)
    {
        $hidden = self::hiddenIdsQuery($user ?? auth()->user());

        if ($hidden) {
            $query->where(fn ($inner) => $inner->whereNull($column)->orWhereNotIn($column, $hidden));
        }

        return $query;
    }

    /** @return list<int> */
    public static function hiddenIds(?User $user): array
    {
        $query = self::hiddenIdsQuery($user);

        return $query ? $query->distinct()->pluck('customer_id')->map(fn ($id) => (int) $id)->all() : [];
    }

    public static function canSee(?User $user, int $customerId): bool
    {
        $query = self::hiddenIdsQuery($user);

        return ! $query || ! $query->where('assigned.customer_id', $customerId)->exists();
    }
}
