<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PhonePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('phone_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('phone_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('phone_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('phone_delete');
    }
}
