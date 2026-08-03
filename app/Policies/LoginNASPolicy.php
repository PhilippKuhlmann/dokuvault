<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoginNASPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('loginnas_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('loginnas_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('loginnas_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('loginnas_delete');
    }
}
