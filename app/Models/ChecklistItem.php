<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistItem extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'needs_approval' => 'boolean',
        'approved_at' => 'datetime',
        'done_at' => 'datetime',
    ];

    public function checklist()
    {
        return $this->belongsTo(Checklist::class);
    }

    public function doneBy()
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    /** Waiting for the customer's management before IT may act. */
    public function awaitsApproval(): bool
    {
        return $this->needs_approval && $this->approved_at === null;
    }
}
