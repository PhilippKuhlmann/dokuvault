<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A service from the admin catalog - name plus colour.
 *
 * Deliberately no tenant: like operating systems and mail providers the
 * catalog applies to the whole installation.
 */
class Service extends Model
{
    use HasFactory;
    use TracksChanges;

    protected $guarded = [];

    /**
     * Hex colour per service name, in lower case.
     *
     * Loaded once instead of queried per chip: the device lists draw hundreds
     * of chips per page.
     */
    public static function farbzuordnung(): array
    {
        return static::einmal('dienste.farben', fn () => static::pluck('color', 'name')
            ->mapWithKeys(fn ($farbe, $name) => [mb_strtolower(trim($name)) => $farbe])
            ->all());
    }

    /**
     * Description per service name, in lower case - for the title on the chip.
     * Like farbzuordnung() loaded once instead of queried per chip.
     */
    public static function beschreibungen(): array
    {
        return static::einmal('dienste.beschreibungen', fn () => static::whereNotNull('description')
            ->pluck('description', 'name')
            ->mapWithKeys(fn ($text, $name) => [mb_strtolower(trim($name)) => $text])
            ->all());
    }

    /**
     * The catalog for the select in the device form: name, description and
     * the finished chip colour with a readable text colour.
     */
    public static function katalog(): array
    {
        return static::einmal('dienste.katalog', fn () => static::orderBy('name')->get()
            ->map(fn ($dienst) => [
                'name' => $dienst->name,
                'description' => $dienst->description,
                'color' => $dienst->color,
                'stil' => static::kachelStil($dienst->color),
            ])
            ->all());
    }

    /**
     * Load once per request instead of per chip - the device lists draw
     * hundreds of them.
     *
     * Kept in the container and not in a static variable: that lives as long
     * as the process, and in the test suite every test runs in the same one.
     * A test then saw the catalog of an earlier one.
     */
    protected static function einmal(string $schluessel, callable $laden): array
    {
        if (! app()->bound($schluessel)) {
            app()->instance($schluessel, $laden());
        }

        return app()->make($schluessel);
    }

    /**
     * Background and text colour of a chip. The text follows the perceived
     * brightness (ITU-R BT.601), or it would be unreadable on light or dark
     * chips - the same in both themes, because the background is fixed.
     */
    public static function kachelStil(?string $hex): ?string
    {
        $farben = static::kachelFarben($hex);

        return $farben ? 'background-color: '.$farben['farbe'].'; color: '.$farben['schrift'].';' : null;
    }

    /**
     * The same computation as values instead of CSS - for the iOS app, which
     * would otherwise rebuild the rule and drift from it on first touch.
     *
     * @return array{farbe: string, schrift: string}|null
     */
    public static function kachelFarben(?string $hex): ?array
    {
        if (! is_string($hex) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        [$r, $g, $b] = array_map(fn ($teil) => hexdec($teil) / 255, str_split(substr($hex, 1), 2));
        $helligkeit = 0.299 * $r + 0.587 * $g + 0.114 * $b;

        return ['farbe' => $hex, 'schrift' => $helligkeit > 0.6 ? '#111827' : '#ffffff'];
    }
}
