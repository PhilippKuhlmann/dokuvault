<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LicenseSoftwarePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('licensesoftware_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('licensesoftware_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('licensesoftware_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('licensesoftware_delete');
    }
}
