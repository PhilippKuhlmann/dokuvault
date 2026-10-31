<?php

namespace App\Models\Concerns;

use App\Models\IpAddress;

/**
 * Lets a device have any number of additional IP addresses (e.g. gateway IPs
 * per VLAN on routers and firewalls), optionally assigned to a VLAN.
 */
trait HasIpAddresses
{
    public function ipAddresses()
    {
        return $this->morphMany(IpAddress::class, 'ipable');
    }
}
