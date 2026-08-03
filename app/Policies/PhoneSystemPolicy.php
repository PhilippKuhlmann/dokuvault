<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PhoneSystemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('phonesystem_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('phonesystem_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('phonesystem_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('phonesystem_delete');
    }
}
