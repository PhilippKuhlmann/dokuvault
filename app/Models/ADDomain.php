<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class ADDomain extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $table = 'ad_domains';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Das DSRM-Kennwort verschluesselt, wie die uebrigen Geraetekennwoerter.
     *
     * Hier stand vorher ein Accessor namens password(). Eine Spalte dieses
     * Namens gibt es in ad_domains nicht - er lief ins Leere, und das Kennwort
     * stand im Klartext in der Datenbank. Der Methodenname muss zur Spalte
     * passen, sonst wiederholt sich genau das.
     */
    protected function dsrmpassword(): Attribute
    {
        return new Attribute(
            get: fn ($value) => filled($value) ? Crypt::decryptString($value) : null,
            // Leer bleibt leer statt zu einem Chiffrat ueber nichts zu werden.
            // Kein null: Die Spalte ist NOT NULL, und das Feld ist Pflicht -
            // leer kommt hier ohnehin nur ueber den Seeder oder einen Test an.
            set: fn ($value) => filled($value) ? Crypt::encryptString($value) : '',
        );
    }
}
