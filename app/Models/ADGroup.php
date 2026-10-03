<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ADGroup extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $table = 'ad_groups';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /** Direct members (ad_group_ad_user). */
    public function users()
    {
        return $this->belongsToMany(ADUser::class, 'ad_group_ad_user', 'ad_group_id', 'ad_user_id')
            ->withTimestamps()
            ->orderBy('username');
    }
}
