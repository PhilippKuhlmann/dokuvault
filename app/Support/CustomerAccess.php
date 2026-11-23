<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
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

    /** As the choice on the customer names it - literal __() calls, so the translation test finds them. */
    public static function label(string $level): string
    {
        return match ($level) {
            self::READ => __('Lesen'),
            self::READ_NO_SECRETS => __('Lesen ohne Kennwörter'),
            default => __('Vollzugriff'),
        };
    }

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

    /*
     * The levels (piece 2). The role says what someone may do at all; the
     * level of their team on a customer may only take something away:
     *
     *   full             - what the role allows
     *   read             - see and reveal passwords, nothing created,
     *                      changed or deleted
     *   read_no_secrets  - see, but no password, key or PIN in plaintext
     *
     * With two teams on one customer the higher level counts. A customer
     * without a team, a customer login and a call without a user: full -
     * there the role alone decides, as before.
     */

    /** The user's level on the customer, or null when it is hidden from them. */
    public static function levelFor(?User $user, ?int $customerId): ?string
    {
        if (! $user || $user->customer_id || ! $customerId || ! self::anyAssigned()) {
            return self::FULL;
        }

        // Asked for every @can on a page - once per request and customer.
        // A new request (and so every test call) starts empty; a change of
        // teams within one request calls flush().
        $cache = request()->attributes->get('customer_access.levels', []);
        $key = $user->getKey().':'.$customerId;

        if (! array_key_exists($key, $cache)) {
            $rows = DB::table('customer_team')
                ->leftJoin('team_user', fn ($join) => $join->on('team_user.team_id', '=', 'customer_team.team_id')
                    ->where('team_user.user_id', $user->getKey()))
                ->where('customer_team.customer_id', $customerId)
                ->get(['customer_team.level', 'team_user.user_id']);

            $mine = $rows->whereNotNull('user_id')->pluck('level')->all();

            $cache[$key] = match (true) {
                $rows->isEmpty() => self::FULL,
                $mine === [] => null,
                default => collect(self::LEVELS)->first(fn ($level) => in_array($level, $mine, true)) ?? self::READ_NO_SECRETS,
            };

            request()->attributes->set('customer_access.levels', $cache);
        }

        return $cache[$key];
    }

    /**
     * Whether any customer has a team at all - once per request. Most
     * installations have none: then every level is full without asking per
     * field or per card (the lists count their queries).
     */
    public static function anyAssigned(): bool
    {
        $known = request()->attributes->get('customer_access.any');

        if ($known === null) {
            $known = DB::table('customer_team')->exists();
            request()->attributes->set('customer_access.any', $known);
        }

        return $known;
    }

    public static function canWrite(?User $user, ?int $customerId): bool
    {
        return self::levelFor($user, $customerId) === self::FULL;
    }

    /**
     * Stops a write of the signed-in user on a customer where their teams
     * only read - a 403, whatever path the write came by.
     */
    public static function assertWritable(?int $customerId): void
    {
        if ($customerId && ! self::canWrite(auth()->user(), $customerId)) {
            throw new AuthorizationException(__('Bei diesem Kunden hast du nur Lesezugriff.'));
        }
    }

    public static function canRevealSecrets(?User $user, ?int $customerId): bool
    {
        return in_array(self::levelFor($user, $customerId), [self::FULL, self::READ], true);
    }

    /**
     * The customer the request works on: the {customer} of the route, or the
     * one a Livewire component announced (ScopedToCustomer) - /livewire/update
     * has no customer in its route.
     */
    public static function currentCustomerId(): ?int
    {
        $route = request()->route('customer');

        if ($route instanceof Customer) {
            return (int) $route->getKey();
        }

        $announced = request()->attributes->get('customer_access.current');

        return $announced ? (int) $announced : null;
    }

    public static function announce(int $customerId): void
    {
        request()->attributes->set('customer_access.current', $customerId);
    }

    /** After a change of teams or levels within the same request. */
    public static function flush(): void
    {
        request()->attributes->remove('customer_access.levels');
        request()->attributes->remove('customer_access.any');
    }

    /** Abilities that write: the four per kind end in _create, _update, _delete. */
    public static function writes(string $ability): bool
    {
        return (bool) preg_match('/_(create|update|delete)$/', $ability)
            || in_array($ability, self::WRITING_ABILITIES, true);
    }

    /** Writing abilities outside the scheme "kind_action". */
    private const WRITING_ABILITIES = ['agent_manage', 'checklist_manage', 'checklist_request', 'checklist_approve'];
}
