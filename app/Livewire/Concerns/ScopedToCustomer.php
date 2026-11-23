<?php

namespace App\Livewire\Concerns;

use App\Models\Customer;
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

        // From here on the gates know the customer (CustomerAccess level).
        CustomerAccess::announce($customerId);
    }

    /**
     * Every later request of the component (/livewire/update) carries no
     * customer in its route: the component announces the one it holds, so
     * a gate asked in an action or in render sees the level of its team.
     */
    public function hydrateScopedToCustomer(): void
    {
        foreach (['customerId', 'customer_id', 'kundeId'] as $property) {
            if (property_exists($this, $property) && $this->{$property}) {
                CustomerAccess::announce((int) $this->{$property});

                return;
            }
        }

        if (property_exists($this, 'customer') && $this->customer instanceof Customer) {
            CustomerAccess::announce((int) $this->customer->getKey());
        }
    }
}
