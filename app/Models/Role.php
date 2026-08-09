<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public const IS_ADMIN = 1;

    public const IS_TECHNIKER = 10;

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function assignPermission($permission)
    {
        return $this->permissions()->save($permission);
    }
}
