<?php

namespace App\Models\Concerns;

use App\Models\Customer;
use App\Models\Scopes\OwnCustomerScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * For every model whose table has a customer_id.
 *
 * Two things come with it: forCustomer() as the one spelling of "of this
 * customer", and OwnCustomerScope, which keeps a customer login inside its
 * own customer even where a query forgot to say so.
 *
 * TenantModelsTest fails for a model that has the column and not the trait -
 * a new table cannot slip past it.
 */
trait OwnedByCustomer
{
    public static function bootOwnedByCustomer(): void
    {
        static::addGlobalScope(new OwnCustomerScope);
    }

    /** Whether rows without a customer are shared defaults, visible to every customer. */
    public static function sharesRowsWithoutCustomer(): bool
    {
        return false;
    }

    public function scopeForCustomer(Builder $query, Customer|int $customer): Builder
    {
        return $query->where(
            $query->qualifyColumn('customer_id'),
            $customer instanceof Customer ? $customer->getKey() : $customer,
        );
    }
}
