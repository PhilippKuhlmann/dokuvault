<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCustomer;
use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class LoginGeneral extends Model
{
    use BelongsToCustomer;
    use HasFactory, SoftDeletes;
    use TracksChanges;

    public const KIND = 'password';

    /** Name of the filter - whoever lifts it should not have to guess. */
    public const SCOPE = 'kennwort';

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * The own list holds passwords only, no SSH keys.
     *
     * SshKey extends this class and overrides booted() without
     * parent::booted() - so its own filter applies there instead of this one.
     * Whoever adds something here has to add it there as well.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(self::SCOPE, fn (Builder $abfrage) => $abfrage->where('kind', self::KIND));
    }

    protected function password(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => Crypt::encryptString($value),
        );
    }

    /**
     * Foreign key stated explicitly: SshKey inherits this relation, and
     * Eloquent would otherwise derive the name from the class - there is no "ssh_key_id".
     */
    /**
     * Whether this entry is an SSH key.
     *
     * It sits here and not on SshKey: through a link a LoginGeneral always
     * comes back, even when a key is behind it (see CredentialLink::login).
     * The display on the device has to tell the two apart.
     */
    public function istSchluessel(): bool
    {
        return $this->kind === SshKey::KIND;
    }

    public function links()
    {
        return $this->hasMany(CredentialLink::class, 'login_general_id');
    }

    /**
     * Links to objects that still exist.
     *
     * A device in the trash keeps its link - it is back on restore - but does
     * not show up here.
     */
    public function verwendungen()
    {
        // Use the preloaded relation if there is one - in the list every row
        // would otherwise carry a query of its own.
        $links = $this->relationLoaded('links')
            ? $this->links
            : $this->links()->with('credentialable')->get();

        return $links->filter(fn ($link) => $link->credentialable !== null)->values();
    }

    /**
     * One line for list and PDF: "SRV-01 (Server), VM-02 (VM)".
     *
     * Sorted, because the polymorphic preloading groups by kind and the list
     * would otherwise come out in a different order depending on the call.
     */
    public function verwendetBei(): string
    {
        return $this->verwendungen()->map->zielBezeichnung()->sort()->implode(', ');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
