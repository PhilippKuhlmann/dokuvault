<?php

namespace App\Support\Directory;

use LDAP\Connection;

/**
 * An on-premises Active Directory over LDAPS (or STARTTLS), with PHP's own
 * ldap functions - no package: LdapRecord demands ext-ldap in composer.json,
 * and composer install would stop on every server without it. This way the
 * directory sign-in is optional for real; without the extension the
 * settings page says so.
 *
 * The flow: bind as the service account, find the user, bind as the user
 * with the given password (that is the password check - nothing is
 * compared or stored here), back to the service account, read the groups.
 */
class PhpLdapClient implements DirectoryClient
{
    /** LDAP_MATCHING_RULE_IN_CHAIN: membership through nested groups as well. */
    private const IN_CHAIN = '1.2.840.113556.1.4.1941';

    /** Not disabled: bit 2 (ACCOUNTDISABLE) of userAccountControl unset. */
    private const NOT_DISABLED = '(!(userAccountControl:1.2.840.113556.1.4.803:=2))';

    /** LDAP result "invalid credentials" - a wrong password, not an outage. */
    private const INVALID_CREDENTIALS = 49;

    public function authenticate(string $login, string $password): ?DirectoryUser
    {
        // An empty password makes an anonymous bind - and that "succeeds".
        // It must never count as a sign-in.
        if ($password === '' || trim($login) === '') {
            return null;
        }

        $c = $this->connect();

        try {
            $this->bindService($c);

            $eintrag = $this->findUser($c, $login);
            if ($eintrag === null) {
                return null;
            }

            if (! @ldap_bind($c, $eintrag['dn'], $password)) {
                if (ldap_errno($c) === self::INVALID_CREDENTIALS) {
                    return null;
                }

                throw new DirectoryUnavailable($this->reason($c));
            }

            // Back to the service account: an ordinary user may not be
            // allowed to read every group.
            $this->bindService($c);

            return new DirectoryUser(
                guid: $eintrag['guid'],
                username: $eintrag['sam'],
                upn: $eintrag['upn'],
                name: $eintrag['name'],
                email: $eintrag['mail'],
                groups: $this->groupsOf($c, $eintrag['dn']),
            );
        } finally {
            @ldap_unbind($c);
        }
    }

    /** Paged: AD hands out at most 1000 entries per search (MaxPageSize). */
    public function groups(): array
    {
        $c = $this->connect();

        try {
            $this->bindService($c);

            $gruppen = [];
            $cookie = '';

            do {
                $suche = @ldap_search($c, (string) LdapConfig::baseDn(), '(objectClass=group)', ['samaccountname', 'cn'],
                    0, 0, 0, LDAP_DEREF_NEVER,
                    [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => 500, 'cookie' => $cookie]]]);

                if ($suche === false) {
                    throw new DirectoryUnavailable(__('Die Gruppen konnten nicht gelesen werden: :grund', ['grund' => $this->reason($c)]));
                }

                $eintraege = ldap_get_entries($c, $suche);
                for ($i = 0; $i < ($eintraege['count'] ?? 0); $i++) {
                    $gruppen[] = [
                        'dn' => $eintraege[$i]['dn'],
                        'name' => $eintraege[$i]['samaccountname'][0] ?? $eintraege[$i]['cn'][0] ?? $eintraege[$i]['dn'],
                    ];
                }

                $steuerung = [];
                ldap_parse_result($c, $suche, $code, $dn, $text, $verweise, $steuerung);
                $cookie = $steuerung[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
            } while ($cookie !== '' && count($gruppen) < 10000);

            usort($gruppen, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

            return $gruppen;
        } finally {
            @ldap_unbind($c);
        }
    }

    public function test(): string
    {
        $c = $this->connect();

        try {
            $this->bindService($c);

            if (@ldap_read($c, (string) LdapConfig::baseDn(), '(objectClass=*)', ['dn']) === false) {
                throw new DirectoryUnavailable(__('Der Basis-DN wurde nicht gefunden: :grund', ['grund' => $this->reason($c)]));
            }

            return __('Verbindung steht: :server, Dienstkonto angemeldet, Basis-DN gefunden.', ['server' => LdapConfig::host()]);
        } finally {
            @ldap_unbind($c);
        }
    }

    /** @return Connection */
    private function connect()
    {
        if (! LdapConfig::extensionLoaded()) {
            throw new DirectoryUnavailable(__('PHP kann kein LDAP: Die Erweiterung „ldap“ fehlt auf dem Server.'));
        }

        $host = trim((string) LdapConfig::host());
        if ($host === '') {
            throw new DirectoryUnavailable(__('Kein Server eingetragen.'));
        }

        // Process-wide in libldap, so set on every connection: a setting
        // switched in the admin area then holds without a restart.
        ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LdapConfig::verifyCert() ? LDAP_OPT_X_TLS_DEMAND : LDAP_OPT_X_TLS_NEVER);

        $schema = LdapConfig::encryption() === 'ldaps' ? 'ldaps' : 'ldap';
        $c = @ldap_connect($schema.'://'.$host.':'.LdapConfig::port());
        if ($c === false) {
            throw new DirectoryUnavailable(__('Die Server-Adresse ist ungültig.'));
        }

        ldap_set_option($c, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($c, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($c, LDAP_OPT_NETWORK_TIMEOUT, 5);
        ldap_set_option($c, LDAP_OPT_TIMELIMIT, 10);

        if (LdapConfig::encryption() === 'starttls' && ! @ldap_start_tls($c)) {
            throw new DirectoryUnavailable(__('STARTTLS fehlgeschlagen: :grund', ['grund' => $this->reason($c)]));
        }

        return $c;
    }

    /** @param  Connection  $c */
    private function bindService($c): void
    {
        $dn = LdapConfig::bindDn();
        $kennwort = LdapConfig::bindPassword();

        if (blank($dn) || blank($kennwort)) {
            throw new DirectoryUnavailable(__('Kein Dienstkonto eingetragen.'));
        }

        if (! @ldap_bind($c, $dn, $kennwort)) {
            throw new DirectoryUnavailable(__('Das Dienstkonto wurde abgewiesen: :grund', ['grund' => $this->reason($c)]));
        }
    }

    /**
     * The one enabled user account matching the login. None, or more than
     * one (an ambiguous mail address), is no match.
     *
     * @param  Connection  $c
     * @return array{dn: string, guid: string, sam: string, upn: ?string, name: ?string, mail: ?string}|null
     */
    private function findUser($c, string $login): ?array
    {
        $login = trim($login);

        if (str_contains($login, '\\')) {
            $bedingung = 'sAMAccountName='.$this->esc(substr($login, strrpos($login, '\\') + 1));
        } elseif (str_contains($login, '@')) {
            $wert = $this->esc($login);
            $bedingung = '|(userPrincipalName='.$wert.')(mail='.$wert.')';
        } else {
            $bedingung = 'sAMAccountName='.$this->esc($login);
        }

        $filter = '(&(objectCategory=person)(objectClass=user)('.$bedingung.')'.self::NOT_DISABLED.')';
        $suche = @ldap_search($c, (string) LdapConfig::baseDn(), $filter,
            ['objectguid', 'samaccountname', 'userprincipalname', 'displayname', 'mail'], 0, 2);

        if ($suche === false) {
            throw new DirectoryUnavailable(__('Die Benutzersuche ist fehlgeschlagen: :grund', ['grund' => $this->reason($c)]));
        }

        $eintraege = ldap_get_entries($c, $suche);
        if (($eintraege['count'] ?? 0) !== 1) {
            return null;
        }

        $e = $eintraege[0];
        $guid = bin2hex($e['objectguid'][0] ?? '');

        return $guid === '' ? null : [
            'dn' => $e['dn'],
            'guid' => $guid,
            'sam' => $e['samaccountname'][0] ?? $login,
            'upn' => $e['userprincipalname'][0] ?? null,
            'name' => $e['displayname'][0] ?? null,
            'mail' => $e['mail'][0] ?? null,
        ];
    }

    /**
     * Every group the user is in, nested groups included.
     *
     * @param  Connection  $c
     * @return array<int, array{dn: string, name: ?string, cn: ?string}>
     */
    private function groupsOf($c, string $userDn): array
    {
        $filter = '(&(objectClass=group)(member:'.self::IN_CHAIN.':='.$this->esc($userDn).'))';
        $suche = @ldap_search($c, (string) LdapConfig::baseDn(), $filter, ['samaccountname', 'cn'], 0, 1000);

        if ($suche === false) {
            throw new DirectoryUnavailable(__('Die Gruppen konnten nicht gelesen werden: :grund', ['grund' => $this->reason($c)]));
        }

        $eintraege = ldap_get_entries($c, $suche);
        $gruppen = [];

        for ($i = 0; $i < ($eintraege['count'] ?? 0); $i++) {
            $gruppen[] = [
                'dn' => $eintraege[$i]['dn'],
                'name' => $eintraege[$i]['samaccountname'][0] ?? null,
                'cn' => $eintraege[$i]['cn'][0] ?? null,
            ];
        }

        return $gruppen;
    }

    private function esc(string $wert): string
    {
        return ldap_escape($wert, '', LDAP_ESCAPE_FILTER);
    }

    /**
     * What the server said - "Can't contact LDAP server" helps more than
     * "failed".
     *
     * @param  Connection  $c
     */
    private function reason($c): string
    {
        $diagnose = null;
        @ldap_get_option($c, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnose);

        return trim(ldap_error($c).(filled($diagnose) ? ' ('.$diagnose.')' : ''));
    }
}
