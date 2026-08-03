<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MailboxPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('mailbox_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('mailbox_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('mailbox_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('mailbox_delete');
    }
}
