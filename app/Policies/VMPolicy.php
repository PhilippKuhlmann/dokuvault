<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class VMPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('vm_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('vm_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('vm_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('vm_delete');
    }
}
