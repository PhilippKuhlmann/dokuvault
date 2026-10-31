<?php

namespace App\Support;

use App\Models\Setting;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;

/**
 * Show instants in the configured timezone.
 *
 * Storage stays UTC, and that is deliberate. Switching app.timezone instead
 * would make the application write local times into the very columns that
 * already hold UTC: two timezones in one column, with nothing to tell which
 * row is which. The conversion therefore happens on display only.
 *
 * Dates without a time (expiry date, procurement date) deliberately do not
 * run through here: shifting a date by hours can tip it to the day before.
 */
class LocalTime
{
    /** Without a setting it stays with whatever the configuration says. */
    public static function zone(): string
    {
        $eigene = trim((string) Setting::wert(Setting::APP_TIMEZONE));

        return $eigene !== '' && in_array($eigene, DateTimeZone::listIdentifiers(), true)
            ? $eigene
            : (string) config('app.timezone');
    }

    /**
     * An instant in the configured zone.
     *
     * @param  string  $wenn_leer  What stands there when the instant does not
     *                             exist - "never" reads better than an empty cell.
     */
    public static function display(?DateTimeInterface $wert, string $format = 'd.m.Y H:i', string $wenn_leer = ''): string
    {
        if ($wert === null) {
            return $wenn_leer;
        }

        return Carbon::instance($wert)->setTimezone(self::zone())->format($format);
    }

    /**
     * The end of a calendar day in the configured zone, as a UTC instant.
     *
     * For expiry dates picked in a date field: "valid until 08.10." means
     * until midnight here. endOfDay() in UTC stored 23:59:59 UTC - shown in
     * Europe/Berlin that was 01:59 on the 9th, so the list said "09.10." and
     * the token lived two hours too long.
     */
    public static function endOfDay(string|DateTimeInterface $datum): Carbon
    {
        $tag = $datum instanceof DateTimeInterface
            ? Carbon::instance($datum)->setTimezone(self::zone())->toDateString()
            : $datum;

        return Carbon::parse($tag, self::zone())->endOfDay()->utc();
    }

    /**
     * The zones on offer: Europe and UTC.
     *
     * Not all 400 identifiers in the world - the list should stay usable.
     * Anyone who needs another one can set it in the configuration; the
     * setting then overrides it only if it is selectable here as well.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_values(array_unique(array_merge(
            ['UTC'],
            DateTimeZone::listIdentifiers(DateTimeZone::EUROPE),
        )));
    }
}
