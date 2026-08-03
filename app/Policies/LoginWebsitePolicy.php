<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoginWebsitePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('loginwebsite_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('loginwebsite_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('loginwebsite_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('loginwebsite_delete');
    }
}
