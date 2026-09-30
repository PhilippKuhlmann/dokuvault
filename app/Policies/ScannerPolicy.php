<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScannerPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('scanner_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('scanner_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('scanner_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('scanner_delete');
    }
}
