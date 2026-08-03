<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GeneralPolicy
{
    use HandlesAuthorization;

    public function see_hidden(User $user)
    {
        return $user->hasPermission('see_hidden');
    }

    public function create_pdf(User $user)
    {
        return $user->hasPermission('create_pdf');
    }
}
