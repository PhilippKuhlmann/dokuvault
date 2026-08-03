<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UpsPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('ups_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('ups_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('ups_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('ups_delete');
    }
}
