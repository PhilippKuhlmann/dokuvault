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
     * No credentials of its own on the server.
     *
     * In practice a server has several accounts - one for the tax adviser,
     * one for the backup, one for the supplier. They hang on "Logins
     * Allgemein" through HasCredentials like on every other device, instead
     * of sitting in columns or a table of their own. Otherwise
     * dasselbe Dienstkonto auf drei Servern dreimal dokumentiert.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
