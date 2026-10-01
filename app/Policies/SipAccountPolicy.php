<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SipAccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('sipaccount_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('sipaccount_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('sipaccount_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('sipaccount_delete');
    }
}
