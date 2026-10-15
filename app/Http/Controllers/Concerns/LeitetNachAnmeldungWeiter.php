<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Role;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;

/**
 * Wohin es nach einer geglueckten Anmeldung geht.
 *
 * Steht an einer Stelle, weil es zwei Eingaenge gibt: die Anmeldung mit
 * Kennwort und - bei eingeschalteter zweiter Stufe - die mit dem Einmalcode.
 * Beide muessen dasselbe Ziel treffen.
 */
trait LeitetNachAnmeldungWeiter
{
    protected function nachDerAnmeldung(): RedirectResponse
    {
        $nutzer = auth()->user();

        // Nutzer mit fest zugeordnetem Kunden -> direkt zum eigenen
        // Kunden-Dashboard. Alle anderen (Admin, Techniker, ...) haben keinen
        // festen Kunden und landen auf der Kundensuche/Uebersicht.
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
