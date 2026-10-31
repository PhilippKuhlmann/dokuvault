<?php

namespace App\Support;

/**
 * The changelog as the source of the version number.
 *
 * The number in the masthead of the sign-in page and in the sidebar is the
 * topmost heading of changelog.md. That is deliberate: a version that had to
 * be kept in a second place drifts against the changelog sooner or later,
 * and then one of the two is wrong.
 *
 * The file is read once per request. Before, the file_get_contents and its
 * regex sat in a view composer on '*' - and Blade components are views: a
 * page with forty components read the file forty times from disk. None of
 * that was visible, but it was measurable.
 *
 * Deliberately not a cache entry, only remembered for the duration of the
 * request: a cached value outlived editing the changelog, and the version
 * shown would be silently wrong afterwards - until someone clears the cache.
 * One file access per request is the price for that not happening.
 */
class Changelog
{
    protected static ?string $roh = null;

    protected static ?string $version = null;

    /** The complete changelog as it stands on disk. */
    public static function roh(): string
    {
        return static::$roh ??= file_get_contents(base_path('changelog.md'));
    }

    /**
     * The topmost heading, for example "26.09.19".
     *
     * If none is found it stays "Unbekannt" - a guessed number would be
     * worse than none.
     */
    public static function version(): string
    {
        if (static::$version !== null) {
            return static::$version;
        }

        preg_match('/## (\d{2}\.\d{2}\.\d{2})/', static::roh(), $treffer);

        return static::$version = $treffer[1] ?? 'Unbekannt';
    }
}
