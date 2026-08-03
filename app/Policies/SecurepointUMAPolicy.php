<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SecurepointUMAPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('securepointuma_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('securepointuma_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('securepointuma_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('securepointuma_delete');
    }
}
