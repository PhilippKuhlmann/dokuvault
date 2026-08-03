<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RecorderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('recorder_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('recorder_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('recorder_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('recorder_delete');
    }
}
