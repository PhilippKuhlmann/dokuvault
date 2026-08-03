<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoginGeneralPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('logingeneral_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('logingeneral_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('logingeneral_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('logingeneral_delete');
    }
}
