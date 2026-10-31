<?php

namespace App\Support;

/**
 * A URL that can be clicked - or deliberately cannot.
 *
 * What prompted this: several URL fields are checked for their length only,
 * not for their scheme, and they end up as an href on a card. If the value
 * reads "javascript:...", every click runs that code in the session of
 * whoever clicked - and that is rarely the person who entered it.
 *
 * The check happens on display, not only on save: the fields have long held
 * whatever someone entered before there was a rule for it. And the rule must
 * not be strict - they often hold just "192.168.178.1", and that should keep
 * working. It simply is not turned into a link.
 */
class SafeUrl
{
    /**
     * The URL if it is safe to link - null otherwise.
     *
     * An allowlist, and only complete URLs at that: http and https. The other
     * way round one would have to know every scheme a browser executes -
     * javascript, data, vbscript, and whichever comes next.
     *
     * A value starting with "/" expressly does not count. It looked like a
     * path inside the application for one afternoon, until the VLAN list held
     * "/24" - a netmask, promptly rendered as a link to a page "/24". From the
     * value alone the two cannot be told apart. Whoever wants to render a link
     * inside the application passes a complete URL: there the view knows it is
     * one, here it would be a guess.
     */
    public static function sicher(mixed $wert): ?string
    {
        $adresse = trim((string) $wert);

        if ($adresse === '') {
            return null;
        }

        $schema = strtolower((string) parse_url($adresse, PHP_URL_SCHEME));

        return in_array($schema, ['http', 'https'], true) ? $adresse : null;
    }

    /**
     * A path inside this application, such as "/mustermann".
     *
     * Kept apart from sicher(), and that is the point: here the view has
     * already decided that a link is meant - it passes the value under a key
     * of its own. Nothing is guessed any more; the netmask "/24" sits under
     * "CIDR" and never arrives here.
     *
     * It is checked all the same, so the next use does not accidentally lead
     * outside: "//example.test" is protocol-relative, and browsers treat
     * "/\example.test" the same way.
     */
    public static function pfad(mixed $wert): ?string
    {
        $pfad = trim((string) $wert);

        // Browsers drop tabs and line breaks inside a URL, so "/<tab>/host"
        // would turn into "//host".
        if (preg_match('/[\x00-\x1f\x7f]/', $pfad)) {
            return null;
        }

        if (! str_starts_with($pfad, '/')
            || str_starts_with($pfad, '//')
            || str_starts_with($pfad, '/\\')) {
            return null;
        }

        return $pfad;
    }
}
