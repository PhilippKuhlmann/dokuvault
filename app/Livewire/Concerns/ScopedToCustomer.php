<?php

namespace App\Livewire\Concerns;

use App\Support\CustomerAccess;

/**
 * The signed-in user has to belong to the customer the component works
 * with.
 *
 * Why the component has to check that itself: the isCustomer middleware hangs
 * on the routes, and Livewire does not call through them but through
 * /livewire/update. Whoever takes the customer as a parameter takes it from
 * outside.
 *
 * A user without customer_id - technician, admin - works for every customer
 * the teams let them see (CustomerAccess); a hidden one answers 404, as its
 * page does.
 */
trait ScopedToCustomer
{
    protected function onlyOwnCustomer(int $customerId): void
    {
        $nutzer = auth()->user();

        abort_if($nutzer && $nutzer->customer_id && $nutzer->customer_id !== $customerId, 403);
        abort_unless(CustomerAccess::canSee($nutzer, $customerId), 404);
    }
}
