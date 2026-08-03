<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DECTPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('dect_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('dect_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('dect_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('dect_delete');
    }
}
