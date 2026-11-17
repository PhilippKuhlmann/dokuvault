<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Which directory group grants which role.
 *
 * Checked in order of position: the first group a user belongs to decides
 * the role. Higher roles go to the top - someone in "DokuVault-Admins" and
 * "DokuVault-Techniker" becomes an admin.
 *
 * "group" is matched against the distinguished name, the sAMAccountName and
 * the common name of the user's groups, case-insensitively - whatever is
 * easiest to type for the person setting it up.
 */
class DirectoryGroupRole extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'position' => 'integer',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The role the first matching group grants, or null.
     *
     * @param  array<int, array{dn: string, name: ?string, cn: ?string}>  $groups
     */
    public static function roleFor(string $source, array $groups): ?Role
    {
        $namen = collect($groups)
            ->flatMap(fn (array $g) => [$g['dn'] ?? null, $g['name'] ?? null, $g['cn'] ?? null])
            ->filter()
            ->map(fn (string $n) => mb_strtolower(trim($n)))
            ->all();

        return static::where('source', $source)
            ->orderBy('position')->orderBy('id')
            ->with('role')
            ->get()
            ->first(fn (self $zuordnung) => in_array(mb_strtolower(trim($zuordnung->group)), $namen, true))
            ?->role;
    }
}
