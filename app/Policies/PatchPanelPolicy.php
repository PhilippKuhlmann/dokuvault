<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PatchPanelPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('patchpanel_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('patchpanel_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('patchpanel_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('patchpanel_delete');
    }
}
