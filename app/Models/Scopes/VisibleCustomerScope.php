<?php

namespace App\Models\Scopes;

use App\Support\CustomerAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * A customer that belongs to teams the user is not in does not exist for
 * them: not in lists or the search, and its address answers 404 - the
 * route binding by slug goes through this scope too.
 *
 * The customer's data is hidden by OwnCustomerScope, with the same rule
 * (CustomerAccess).
 */
class VisibleCustomerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $hidden = CustomerAccess::hiddenIdsQuery(auth()->user());

        if ($hidden) {
            $builder->whereNotIn($model->qualifyColumn('id'), $hidden);
        }
    }
}
