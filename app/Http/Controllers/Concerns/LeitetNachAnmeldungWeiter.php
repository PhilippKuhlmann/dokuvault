<?php

namespace App\Http\Controllers\Concerns;

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

        return redirect()->intended(RouteServiceProvider::HOME);
    }
}
