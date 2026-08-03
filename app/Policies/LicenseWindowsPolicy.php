<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LicenseWindowsPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('licensewindows_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('licensewindows_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('licensewindows_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('licensewindows_delete');
    }
}
