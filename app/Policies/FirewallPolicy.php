<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FirewallPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('firewall_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('firewall_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('firewall_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('firewall_delete');
    }
}
