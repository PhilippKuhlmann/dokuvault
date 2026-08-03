<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RouterPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('router_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('router_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('router_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('router_delete');
    }
}
