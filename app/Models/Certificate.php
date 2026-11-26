<?php

namespace App\Models;

use App\Models\Concerns\OwnedByCustomer;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certificate extends Model
{
    use HasFactory, SoftDeletes;
    use OwnedByCustomer;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'auto_check' => 'boolean',
        'check_public_only' => 'boolean',
        'checked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Who sets the target decides how the nightly run may reach it: a
        // customer account only public addresses (CertificateCheck), staff
        // internal ones too. Here, so every way of saving is covered.
        static::saving(function (Certificate $certificate) {
            if (! $certificate->exists || $certificate->isDirty(['check_host', 'common_name', 'check_public_only'])) {
                $certificate->check_public_only = (bool) auth()->user()?->hasCustomer();
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
