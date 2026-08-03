<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SecurepointUTMPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('securepointutm_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('securepointutm_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('securepointutm_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('securepointutm_delete');
    }
}
