<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FTPServer extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $table = 'ftp_servers';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Die Zugaenge auf diesem Server.
     *
     * Ein Server hat in der Praxis mehrere - einen fuer den Steuerberater,
     * einen fuer die Webseite, einen fuer das Backup. Frueher war jeder davon
     * eine eigene Server-Zeile mit demselben Host.
     */
    public function users()
    {
        return $this->hasMany(FTPUser::class, 'ftp_server_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
