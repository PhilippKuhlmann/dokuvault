<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MachinePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('machine_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('machine_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('machine_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('machine_delete');
    }
}
