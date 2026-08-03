<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NetworkPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('network_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('network_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('network_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('network_delete');
    }
}
