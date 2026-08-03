<?php

namespace App\Models\Concerns;

use App\Models\IpAddress;

/**
 * Ermöglicht einem Gerät beliebig viele zusätzliche IP-Adressen (z. B. Gateway-IPs
 * je VLAN bei Routern/Firewalls), optional einem VLAN zugeordnet.
 */
trait HasIpAddresses
{
    public function ipAddresses()
    {
        return $this->morphMany(IpAddress::class, 'ipable');
    }
}
