<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class WifiPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('wifi_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('wifi_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('wifi_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('wifi_delete');
    }
}
