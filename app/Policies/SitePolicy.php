<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SitePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('site_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('site_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('site_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('site_delete');
    }
}
