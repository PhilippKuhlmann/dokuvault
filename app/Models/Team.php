<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A group of staff users that customers can be assigned to
 * (App\Support\CustomerAccess). Says on which customers someone works -
 * what they may do there stays the business of their role.
 */
class Team extends Model
{
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->orderBy('name');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class)->withPivot('level')->withTimestamps()->orderBy('name');
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->getKey())->exists();
    }
}
