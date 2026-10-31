<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Links a login entry with a documented object.
 *
 * Deliberately a model of its own instead of morphToMany: that way one can
 * list generically everywhere a login is used - with morphToMany that would
 * need a relation per device kind.
 *
 * No SoftDeletes: a link is not documentation but a reference. Login and
 * device lie in the trash if need be.
 */
class CredentialLink extends Model
{
    use HasFactory;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Unfiltered: a password or an SSH key can hang on a device. Both sit in
     * login_generals, but LoginGeneral sees only the passwords for its own
     * list - without lifting that filter a server with a key attached would
     * have no credentials any more.
     *
     * Only this one filter on purpose: withoutGlobalScopes() would take the
     * trash along too, and then a deleted login would stay on the device.
     */
    public function login()
    {
        return $this->belongsTo(LoginGeneral::class, 'login_general_id')
            ->withoutGlobalScope(LoginGeneral::SCOPE);
    }

    public function credentialable()
    {
        return $this->morphTo();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** URL slug of the target kind - at the same time the key in config('custom.trashables'). */
    public function zielSlug(): string
    {
        return strtolower(class_basename($this->credentialable_type));
    }

    /**
     * What the target is called in a list: "SRV-01 (Server)".
     *
     * Phones and DECT devices have no name column - there the IP takes the
     * place of the name, or only the kind label would stand there. With the
     * FTP server it is the host: that is what one looks for it by. With the
     * AD domain, the domain name.
     */
    public function zielBezeichnung(): string
    {
        $ziel = $this->credentialable;

        if (! $ziel) {
            return '';
        }

        $typ = config('custom.trashables')[$this->zielSlug()][1] ?? class_basename($ziel);
        $name = $ziel->name ?? $ziel->host ?? $ziel->domain ?? $ziel->provider ?? $ziel->ip ?? $ziel->ip1 ?? '#'.$ziel->id;

        return $name.' ('.__($typ).')';
    }

    /**
     * The link itself has no name - it carries that of the device the
     * credentials hang on.
     */
    public function activityName(): ?string
    {
        return $this->zielBezeichnung() ?: null;
    }
}
