<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, SoftDeletes;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public const IS_ADMIN = 1;

    public const IS_TECHNIKER = 10;

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * Ein Recht zuweisen - und die Rolle zurueckgeben, nicht das Recht.
     *
     * Vorher kam das Permission-Model zurueck. Ein
     * "Role::factory()->create()->assignPermission($p)" lieferte damit ein
     * Recht, und wer das Ergebnis fuer die Rolle hielt, setzte eine
     * Rechte-Id als role_id. In den Tests fiel das jahrelang nicht auf, weil
     * beide Tabellen bei 1 anfingen und die Ids sich deckten.
     */
    public function assignPermission($permission): static
    {
        $this->permissions()->save($permission);

        return $this;
    }
}
