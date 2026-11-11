<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * The safety net of the tenant separation.
 *
 * A user who belongs to a customer (users.customer_id) reads and changes
 * rows of that customer only - whatever the query says. Every list and every
 * form filters by customer itself, and that stays the first line: it is what
 * keeps customer A's page free of customer B's devices for a technician,
 * who may see both. But a filter written by hand can be forgotten, and for a
 * customer login a forgotten one means another company's passwords. With
 * this scope the worst a forgotten filter does to them is an empty list.
 *
 * Nobody else is touched: technicians and admins (customer_id null) work
 * for every customer, and the agent API, the queue and the console have no
 * user at all.
 */
class OwnCustomerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $customerId = auth()->user()?->customer_id;

        if (! $customerId) {
            return;
        }

        $column = $model->qualifyColumn('customer_id');

        // Rows without a customer are shared defaults on a few tables (the
        // standard steps of a checklist template). Everywhere else a row
        // without a customer is nobody's and stays hidden.
        if ($model::sharesRowsWithoutCustomer()) {
            $builder->where(fn (Builder $query) => $query->where($column, $customerId)->orWhereNull($column));

            return;
        }

        $builder->where($column, $customerId);
    }
}
