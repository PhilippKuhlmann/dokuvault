<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;
    use TracksChanges;

    public function scopeGetViewAny($query)
    {
        return $query->where('name', 'like', '%_viewAny');
    }

    public function scopeGetCreate($query)
    {
        return $query->where('name', 'like', '%_create');
    }

    public function scopeGetUpdate($query)
    {
        return $query->where('name', 'like', '%_update');
    }

    public function scopeGetDelete($query)
    {
        return $query->where('name', 'like', '%_delete');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}
