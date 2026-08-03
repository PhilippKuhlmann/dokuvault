<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OtherClientPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('otherclient_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('otherclient_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('otherclient_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('otherclient_delete');
    }
}
