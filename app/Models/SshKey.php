<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

/**
 * Ein SSH-Schluessel.
 *
 * Liegt in derselben Tabelle wie die Logins und erbt von ihnen alles, was
 * beide gemeinsam haben: Name, Benutzer, Verknuepfung zu Geraeten,
 * "Verwendet bei". Die Passphrase ist das geerbte Kennwortfeld - inhaltlich
 * ist sie genau das.
 *
 * Getrennt sind die beiden nur ueber 'kind', und zwar in beide Richtungen:
 * Diese Klasse sieht ausschliesslich Schluessel, LoginGeneral in seiner Liste
 * ausschliesslich Kennwoerter. Die Verknuepfung (CredentialLink::login) laedt
 * bewusst ungefiltert - sonst haette ein Server, an dem ein Schluessel haengt,
 * ploetzlich keine Zugangsdaten mehr.
 */
class SshKey extends LoginGeneral
{
    public const KIND = 'sshkey';

    protected $table = 'login_generals';

    protected static function booted(): void
    {
        static::addGlobalScope('sshkey', fn (Builder $abfrage) => $abfrage->where('kind', self::KIND));

        // Ohne das entstuende ueber die Kunden-Relation ein Eintrag mit
        // 'password' - und waere sofort wieder unsichtbar.
        static::creating(function (self $schluessel) {
            $schluessel->kind = self::KIND;
        });
    }

    /** Das Verfahren, wie es in der Liste steht - "ed25519" statt eines leeren Felds. */
    public function verfahrenName(): string
    {
        return config('custom.ssh_key_types')[$this->key_type] ?? '—';
    }

    /**
     * Der private Teil wird verschluesselt abgelegt, wie jedes Kennwort.
     *
     * Der oeffentliche nicht: Er ist zum Verteilen da und muss sich
     * durchsuchen lassen, etwa wenn man ihn in einer authorized_keys wiederfindet.
     */
    protected function privateKey(): Attribute
    {
        return new Attribute(
            get: fn ($value) => ! empty($value) ? Crypt::decryptString($value) : null,
            set: fn ($value) => filled($value) ? Crypt::encryptString($value) : null,
        );
    }
}
