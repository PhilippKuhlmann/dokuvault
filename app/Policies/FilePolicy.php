<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FilePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('file_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('file_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('file_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('file_delete');
    }
}
