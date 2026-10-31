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

    /**
     * Assign a permission - and return the role, not the permission.
     *
     * The Permission model used to come back. A
     * "Role::factory()->create()->assignPermission($p)" therefore returned a
     * permission, and whoever took the result for the role set a permission
     * id as role_id. In the tests that went unnoticed for years, because both
     * tables started at 1 and the ids coincided.
     */
    public function assignPermission($permission): static
    {
        $this->permissions()->save($permission);

        return $this;
    }
}
