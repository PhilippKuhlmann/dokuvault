<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContactPersonPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('contactperson_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('contactperson_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('contactperson_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('contactperson_delete');
    }
}
