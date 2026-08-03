<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class IoTDevicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasPermission('iotdevice_viewAny');
    }

    public function create(User $user)
    {
        return $user->hasPermission('iotdevice_create');
    }

    public function update(User $user)
    {
        return $user->hasPermission('iotdevice_update');
    }

    public function delete(User $user)
    {
        return $user->hasPermission('iotdevice_delete');
    }
}
