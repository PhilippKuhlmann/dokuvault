<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LoginRecorderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('loginrecorder_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('loginrecorder_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('loginrecorder_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('loginrecorder_delete');
    }
}
