<?php

namespace App\Policies;

use App\Models\User;

/**
 * The policy of every kind in config('custom.permissions').
 *
 * All of them ask the same question - does the role carry "{kind}_{ability}"?
 * There used to be one generated class per kind, 47 files with the kind's
 * name as the only difference. Now AuthServiceProvider binds one instance of
 * this class per kind.
 *
 * Tenant separation is not decided here: a permission says what a user may
 * do, the customer filter and OwnCustomerScope say for whom.
 */
class ResourcePolicy
{
    /** @param  string  $resource  the kind in lower case, as in the permission names: "server", "networkswitch" */
    public function __construct(private readonly string $resource) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission($this->resource.'_viewAny');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission($this->resource.'_create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission($this->resource.'_update');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission($this->resource.'_delete');
    }
}
