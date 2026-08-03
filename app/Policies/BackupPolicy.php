<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BackupPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('backup_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('backup_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('backup_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('backup_delete');
    }
}
