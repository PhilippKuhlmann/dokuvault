<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FTPServerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('ftpserver_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('ftpserver_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('ftpserver_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('ftpserver_delete');
    }
}
