<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccesspointPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('accesspoint_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('accesspoint_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('accesspoint_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('accesspoint_delete');
    }
}
