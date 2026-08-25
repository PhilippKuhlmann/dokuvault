<?php

namespace App\Models;

use App\Models\Concerns\HasCredentials;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FTPServer extends Model
{
    use HasCredentials;
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $table = 'ftp_servers';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Keine eigenen Zugangsdaten am Server.
     *
     * Ein Server hat in der Praxis mehrere Zugaenge - einen fuer den
     * Steuerberater, einen fuers Backup, einen fuer den Lieferanten. Sie haengen
     * ueber HasCredentials an "Logins Allgemein" wie bei jedem anderen Geraet,
     * statt in eigenen Spalten oder einer eigenen Tabelle zu stehen. Sonst waere
     * dasselbe Dienstkonto auf drei Servern dreimal dokumentiert.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
