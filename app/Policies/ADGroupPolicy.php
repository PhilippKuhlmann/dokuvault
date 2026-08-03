<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ADGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('adgroup_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('adgroup_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('adgroup_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('adgroup_delete');
    }
}
