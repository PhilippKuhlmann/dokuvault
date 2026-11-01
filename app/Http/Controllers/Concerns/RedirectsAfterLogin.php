<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Role;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;

/**
 * Wohin es nach einer geglueckten Anmeldung geht.
 *
 * It sits in one place because there are two entrances: signing in with a
 * password and - with the second factor switched on - with the one-time code.
 * Beide muessen dasselbe Ziel treffen.
 */
trait RedirectsAfterLogin
{
    protected function nachDerAnmeldung(): RedirectResponse
    {
        $nutzer = auth()->user();

        // Users with a customer assigned to them -> straight to their own
        // Kunden-Dashboard. Alle anderen (Admin, Techniker, ...) haben keinen
        // assigned customer and land on the customer search or overview.
        if ($nutzer->hasCustomer()) {
            return redirect()->intended('/'.$nutzer->customer->slug);
        }

        // Admins straight to the admin dashboard, as RedirectIfAuthenticated
        // does for a logged-in admin opening /login. Only the admin role: a
        // technician with single admin permissions works from the customer
        // search.
        if ($nutzer->role_id === Role::IS_ADMIN) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(RouteServiceProvider::HOME);
    }
}
