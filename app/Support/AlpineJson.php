<?php

namespace App\Support;

/**
 * A PHP value as an Alpine argument in an HTML attribute.
 *
 * Alpine runs in its CSP build, which reads plain JSON but not the
 * JSON.parse('...') that @js writes for arrays - the global JSON is off
 * limits there. The HEX flags keep quotes, < and & out of the string values;
 * e() then escapes the structural quotes for the attribute.
 */
class AlpineJson
{
    public static function attribut(mixed $wert): string
    {
        return e(json_encode(
            $wert,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    }
}
