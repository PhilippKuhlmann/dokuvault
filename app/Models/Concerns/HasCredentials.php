<?php

namespace App\Models\Concerns;

use App\Models\CredentialLink;

/**
 * Verknüpft ein Gerät mit beliebig vielen Logins aus "Logins Allgemein".
 *
 * Dasselbe Passwort steckt oft in mehreren Systemen (ein root-Passwort für alle
 * Linux-VMs). Es soll deshalb einmal dokumentiert und mehrfach verknüpft sein,
 * nicht in jedes Gerät kopiert - sonst findet man beim Wechseln nicht alle Stellen.
 */
trait HasCredentials
{
    public function credentialLinks()
    {
        return $this->morphMany(CredentialLink::class, 'credentialable');
    }

    /**
     * Die verknüpften Logins selbst, ohne die aus dem Papierkorb.
     */
    public function zugangsdaten()
    {
        return $this->credentialLinks()->with('login')->get()
            ->filter(fn ($link) => $link->login !== null)
            ->values();
    }
}
