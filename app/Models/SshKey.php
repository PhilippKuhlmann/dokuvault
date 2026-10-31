<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

/**
 * An SSH key.
 *
 * Sits in the same table as the logins and inherits from them everything
 * the two have in common: name, user, link to devices, "used at". The
 * passphrase is the inherited password field - and that is exactly what it
 * is.
 *
 * The two are kept apart through 'kind' alone, and in both directions: this
 * class sees keys only, LoginGeneral in its list sees passwords only. The
 * link (CredentialLink::login) loads deliberately unfiltered - otherwise a
 * server with a key attached would suddenly have no credentials at all.
 * ploetzlich keine Zugangsdaten mehr.
 */
class SshKey extends LoginGeneral
{
    public const KIND = 'sshkey';

    protected $table = 'login_generals';

    protected static function booted(): void
    {
        static::addGlobalScope('sshkey', fn (Builder $abfrage) => $abfrage->where('kind', self::KIND));

        // Without it the customer relation would create an entry with
        // 'password' - and it would be invisible again at once.
        static::creating(function (self $schluessel) {
            $schluessel->kind = self::KIND;
        });

        // Recomputed on every save: the fingerprint follows entirely from the
        // public key. As a derived value it cannot drift from it - an input
        // field could.
        static::saving(function (self $schluessel) {
            $schluessel->fingerprint = self::fingerprintVon($schluessel->public_key);
        });
    }

    /**
     * The SHA256 fingerprint, as "ssh-keygen -lf" prints it.
     *
     * Computed in PHP and not through ssh-keygen: it is the base64 hash of
     * the key block, no more - a subprocess per list row would be out of
     * proportion for that. Verified against ssh-keygen in the test.
     */
    public static function fingerprintVon(?string $oeffentlich): ?string
    {
        $teile = preg_split('/\s+/', trim((string) $oeffentlich), -1, PREG_SPLIT_NO_EMPTY);

        // Without an algorithm and a block it is not a key but text - then
        // there would be a hash but no fingerprint.
        if (count($teile) < 2) {
            return null;
        }

        $block = base64_decode($teile[1], true);

        if ($block === false || $block === '') {
            return null;
        }

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $block, true)), '=');
    }

    /** The algorithm as it stands in the list - "ed25519" instead of an empty field. */
    public function algorithmName(): string
    {
        return config('custom.ssh_key_types')[$this->key_type] ?? '—';
    }

    /**
     * The private part is stored encrypted, like every password.
     *
     * The public one is not: it is there to be handed out and has to be
     * searchable, for instance when finding it again in an authorized_keys.
     */
    protected function privateKey(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => filled($value) ? Crypt::encryptString($value) : null,
        );
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
