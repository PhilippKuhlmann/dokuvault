<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Domain extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'auto_check' => 'boolean',
        'checked_at' => 'datetime',
        'dkim' => 'array',
        'ptr' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
