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

        // Bei jedem Speichern neu: Der Fingerprint ergibt sich vollstaendig aus
        // dem oeffentlichen Schluessel. Als abgeleiteter Wert kann er so nicht
        // von ihm abweichen - ein Eingabefeld koennte das.
        static::saving(function (self $schluessel) {
            $schluessel->fingerprint = self::fingerprintVon($schluessel->public_key);
        });
    }

    /**
     * Der SHA256-Fingerprint, wie ihn "ssh-keygen -lf" ausgibt.
     *
     * In PHP gerechnet und nicht ueber ssh-keygen: Es ist der Base64-Hash des
     * Schluesselblocks, mehr nicht - ein Unterprozess je Listenzeile waere
     * dafuer unverhaeltnismaessig. Geprueft gegen ssh-keygen im Test.
     */
    public static function fingerprintVon(?string $oeffentlich): ?string
    {
        $teile = preg_split('/\s+/', trim((string) $oeffentlich), -1, PREG_SPLIT_NO_EMPTY);

        // Ohne Verfahren und Block ist es kein Schluessel, sondern Text - dann
        // gaebe es zwar einen Hash, aber keinen Fingerprint.
        if (count($teile) < 2) {
            return null;
        }

        $block = base64_decode($teile[1], true);

        if ($block === false || $block === '') {
            return null;
        }

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $block, true)), '=');
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
