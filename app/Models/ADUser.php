<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class ADUser extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\TracksChanges;

    protected $table = 'ad_users';

    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    protected function password(): Attribute
    {
        return new Attribute(
            get: fn ($value) => !empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => !empty($value) ? Crypt::encryptString($value) : null,
        );
    }

}
