<?php

namespace App\Support;

/**
 * Content type for a stored image from a fixed list, by file extension.
 *
 * Not what the file system guesses from the content: the formats are the
 * ones config('custom.bild_formate') allows, and anything else is served as
 * an opaque download.
 */
class BildTyp
{
    private const TYPEN = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    public static function fuer(string $pfad): string
    {
        return self::TYPEN[strtolower(pathinfo($pfad, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }
}
