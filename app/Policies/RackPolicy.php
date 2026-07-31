<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RackPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('rack_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('rack_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('rack_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('rack_delete');
    }
}
