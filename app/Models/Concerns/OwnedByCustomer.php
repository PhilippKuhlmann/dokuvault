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
            // Moving a row out of a customer is a write on that customer too.
            if ($model->exists && $model->isDirty('customer_id')) {
                CustomerAccess::assertWritable($model->getOriginal('customer_id'));
            }
        };
        static::saving($guard);
        static::deleting($guard);
        if (method_exists(static::class, 'restoring')) {
            static::restoring($guard);
        }
    }

    /**
     * Every secret column stays out of toArray() and JSON - whatever the
     * model declares itself. The public API returned models as they are,
     * and only the server listed its passwords in $hidden: a Wi-Fi key, a
     * USC-PIN or a mailbox password would have gone out in plaintext the
     * moment an endpoint returned such a model. Read in code (->password)
     * they are still there; only the serialized form leaves them out.
     */
    public function initializeOwnedByCustomer(): void
    {
        $this->mergeHidden(array_merge(config('custom.secret_columns', []), self::OTHER_SECRETS));
    }

    /**
     * Encrypted, but not in secret_columns (that list also drives the
     * password history, and these are no passwords one changes there).
     */
    public const OTHER_SECRETS = ['fixed_password', 'scan_communities'];

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
