<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailboxProvider extends Model
{
    use HasFactory;
    use TracksChanges;

    protected $guarded = [];

    public function mailboxes()
    {
        return $this->hasMany(Mailbox::class);
    }
}
