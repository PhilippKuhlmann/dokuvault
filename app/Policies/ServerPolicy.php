<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('server_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('server_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('server_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('server_delete');
    }
}
