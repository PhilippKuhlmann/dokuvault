<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ein Ziel, in das gescannt wird: Netzwerkordner, E-Mail, FTP, Cloud.
 *
 * Ein eigener Eintrag beim Kunden und kein Feld am Scanner: Dasselbe Ziel ist
 * oft auf mehreren Geraeten eingerichtet. Aendert sich der Pfad oder das
 * Kennwort, wird es einmal hier geaendert - wie bei den Zugangsdaten.
 */
class ScanTarget extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /** Schluessel => Beschriftung, in der Reihenfolge der Auswahl. */
    public const ARTEN = [
        'smb' => 'Netzwerkordner',
        'email' => 'E-Mail',
        'ftp' => 'FTP / SFTP',
        'cloud' => 'Cloud',
        'other' => 'Sonstiges',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** Die Scanner, auf denen dieses Ziel eingerichtet ist. */
    public function scanners()
    {
        return $this->belongsToMany(Scanner::class)->withTimestamps()->orderBy('name');
    }

    /** Die Anmeldung am Ziel (FTP-Konto, Freigabe-Benutzer), falls noetig. */
    public function login()
    {
        return $this->belongsTo(LoginGeneral::class, 'login_general_id');
    }

    public function artName(): string
    {
        return __(self::ARTEN[$this->kind] ?? self::ARTEN['other']);
    }
}
