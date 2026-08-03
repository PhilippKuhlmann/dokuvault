<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LicenseAccessPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('licenseaccess_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('licenseaccess_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('licenseaccess_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('licenseaccess_delete');
    }
}
