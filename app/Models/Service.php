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
        static $zuordnung = null;

        return $zuordnung ??= static::pluck('color', 'name')
            ->mapWithKeys(fn ($farbe, $name) => [mb_strtolower(trim($name)) => $farbe])
            ->all();
    }
}
