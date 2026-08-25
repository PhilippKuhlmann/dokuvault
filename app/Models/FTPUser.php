<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

/**
 * Ein Zugang auf einem FTP-Server.
 *
 * Eigene Zeile statt Spalten am Server: Ein Server hat in der Praxis mehrere
 * Zugaenge - einen fuer den Steuerberater, einen fuer die Webseite, einen fuer
 * das Backup. Vorher stand der Host bei jedem davon erneut da und konnte
 * auseinanderlaufen.
 */
class FTPUser extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $table = 'ftp_users';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    protected function password(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => Crypt::encryptString($value),
        );
    }

    public function server()
    {
        return $this->belongsTo(FTPServer::class, 'ftp_server_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Woran man den Zugang im Protokoll und im Papierkorb erkennt.
     *
     * Der Benutzername, nicht die Id: "FTPUser #7" sagt niemandem etwas.
     */
    public function protokollName(): ?string
    {
        return $this->username;
    }
}
