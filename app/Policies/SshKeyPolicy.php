<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SshKeyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('sshkey_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('sshkey_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('sshkey_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('sshkey_delete');
    }
}
