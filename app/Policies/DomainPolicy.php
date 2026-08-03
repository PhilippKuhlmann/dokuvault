<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DomainPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('domain_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('domain_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('domain_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('domain_delete');
    }
}
