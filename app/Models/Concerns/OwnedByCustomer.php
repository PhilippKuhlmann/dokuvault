<?php

namespace App\Models\Concerns;

use App\Models\Customer;
use App\Models\Scopes\OwnCustomerScope;
use App\Support\CustomerAccess;
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

        // The safety net of the level "read" (CustomerAccess): whatever a
        // button forgot to hide, a row of such a customer is not written by
        // that user - not through a form, Livewire or the API. Agents,
        // queue and console have no user and are not touched.
        $guard = function ($model) {
            // A few rows come from reading, not writing: the PDF one orders
            // to read it. Allowed with "read", not without passwords.
            if (static::writtenWhileReading() && CustomerAccess::canRevealSecrets(auth()->user(), $model->customer_id)) {
                return;
            }
            CustomerAccess::assertWritable($model->customer_id);
        };
        static::saving($guard);
        static::deleting($guard);
        if (method_exists(static::class, 'restoring')) {
            static::restoring($guard);
        }
    }

    /** Rows that reading creates (an ordered PDF) - allowed with the level "read". */
    public static function writtenWhileReading(): bool
    {
        return false;
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
