<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Mailbox extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Das Kennwort des Postfachs.
     *
     * Lag bis dahin im Klartext in der Datenbank - als einziges Kennwort dieser Anwendung.
     * Ein Datenbank-Abzug enthielt es damit lesbar.
     */
    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn ($wert) => ! empty($wert) ? Crypt::decryptString($wert) : null,
            set: fn ($wert) => ! empty($wert) ? Crypt::encryptString($wert) : null,
        );
    }

    public function mailboxProvider()
    {
        return $this->belongsTo(MailboxProvider::class);
    }
}
