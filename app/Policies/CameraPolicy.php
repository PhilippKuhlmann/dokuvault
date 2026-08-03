<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CameraPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('camera_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('camera_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('camera_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('camera_delete');
    }
}
