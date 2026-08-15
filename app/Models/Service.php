<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ein Dienst aus dem Katalog der Administration - Name plus Farbe.
 *
 * Bewusst kein Mandantenbezug: Der Katalog gilt wie Betriebssysteme und
 * Mail-Anbieter für die ganze Installation.
 */
class Service extends Model
{
    use HasFactory;
    use TracksChanges;

    protected $guarded = [];

    /**
     * Hex-Farbe je Dienstname, in Kleinschreibung.
     *
     * Einmal geladen statt je Kachel abgefragt: Die Gerätelisten zeichnen
     * hunderte Kacheln pro Seite.
     */
    public static function farbzuordnung(): array
    {
        return static::einmal('dienste.farben', fn () => static::pluck('color', 'name')
            ->mapWithKeys(fn ($farbe, $name) => [mb_strtolower(trim($name)) => $farbe])
            ->all());
    }

    /**
     * Beschreibung je Dienstname, in Kleinschreibung - fuer den Titel an der
     * Kachel. Wie farbzuordnung() einmal geladen statt je Kachel abgefragt.
     */
    public static function beschreibungen(): array
    {
        return static::einmal('dienste.beschreibungen', fn () => static::whereNotNull('description')
            ->pluck('description', 'name')
            ->mapWithKeys(fn ($text, $name) => [mb_strtolower(trim($name)) => $text])
            ->all());
    }

    /**
     * Der Katalog fuer die Auswahl im Geraeteformular: Name, Beschreibung und
     * die fertige Kachelfarbe samt lesbarer Schriftfarbe.
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
     * Einmal je Anfrage laden statt je Kachel - die Gerätelisten zeichnen
     * hunderte davon.
     *
     * Ablage im Container und nicht in einer static-Variablen: Die lebt so
     * lange wie der Prozess, und in der Testsuite laufen alle Tests in
     * demselben. Ein Test sah dann den Katalog eines frueheren.
     */
    protected static function einmal(string $schluessel, callable $laden): array
    {
        if (! app()->bound($schluessel)) {
            app()->instance($schluessel, $laden());
        }

        return app()->make($schluessel);
    }

    /**
     * Hintergrund- und Schriftfarbe einer Kachel. Die Schrift folgt der
     * wahrgenommenen Helligkeit (ITU-R BT.601), sonst waere sie auf hellen oder
     * dunklen Kacheln nicht lesbar - in beiden Themes gleich, weil der
     * Hintergrund fest ist.
     */
    public static function kachelStil(?string $hex): ?string
    {
        if (! is_string($hex) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        [$r, $g, $b] = array_map(fn ($teil) => hexdec($teil) / 255, str_split(substr($hex, 1), 2));
        $helligkeit = 0.299 * $r + 0.587 * $g + 0.114 * $b;

        return 'background-color: '.$hex.'; color: '.($helligkeit > 0.6 ? '#111827' : '#ffffff').';';
    }
}
