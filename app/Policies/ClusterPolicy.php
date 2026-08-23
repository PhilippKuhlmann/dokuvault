<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClusterPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('cluster_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('cluster_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('cluster_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('cluster_delete');
    }
}
