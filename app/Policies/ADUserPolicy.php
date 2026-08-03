<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ADUserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('aduser_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('aduser_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('aduser_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('aduser_delete');
    }
}
