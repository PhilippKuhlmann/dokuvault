<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NetworkSwitchPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('networkswitch_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('networkswitch_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('networkswitch_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('networkswitch_delete');
    }
}
