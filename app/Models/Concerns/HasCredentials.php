<?php

namespace App\Models\Concerns;

use App\Models\CredentialLink;

/**
 * Links a device with any number of logins from "Logins Allgemein".
 *
 * The same password often sits in several systems (one root password for all
 * Linux VMs). It should therefore be documented once and linked many times,
 * not copied into every device - or one misses places when changing it.
 */
trait HasCredentials
{
    public function credentialLinks()
    {
        return $this->morphMany(CredentialLink::class, 'credentialable');
    }

    /**
     * The linked logins themselves, without the ones in the trash.
     */
    public function zugangsdaten()
    {
        // Use the preloaded relation if there is one: in the lists every device
        // would otherwise carry a query of its own (see Controller::getFilteredQuery).
        $links = $this->relationLoaded('credentialLinks')
            ? $this->credentialLinks
            : $this->credentialLinks()->with('login')->get();

        return $links->filter(fn ($link) => $link->login !== null)->values();
    }
}
