<?php

namespace App\Models;

use App\Models\Concerns\OwnedByCustomer;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A target that gets scanned into: network folder, email, FTP, cloud.
 *
 * An entry of its own at the customer and not a field on the scanner: the
 * same target is often set up on several devices. If the path or the password
 * changes, it is changed once here - as with the credentials.
 */
class ScanTarget extends Model
{
    use HasFactory, SoftDeletes;
    use OwnedByCustomer;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /** Key => label, in the order of the select. */
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

    /** The scanners this target is set up on. */
    public function scanners()
    {
        return $this->belongsToMany(Scanner::class)->withTimestamps()->orderBy('name');
    }

    /** The sign-in at the target (FTP account, share user), if needed. */
    public function login()
    {
        return $this->belongsTo(LoginGeneral::class, 'login_general_id');
    }

    public function typeName(): string
    {
        return __(self::ARTEN[$this->kind] ?? self::ARTEN['other']);
    }
}
