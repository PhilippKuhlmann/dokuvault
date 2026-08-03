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

    protected $guarded = [];
}
