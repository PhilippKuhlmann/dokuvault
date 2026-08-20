<?php

namespace App\Models;

use App\Models\Concerns\TracksChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;
    use TracksChanges;

    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /** Endungen je Art - bestimmt Symbol und Farbe in der Liste. */
    private const ARTEN = [
        'pdf' => ['pdf'],
        'bild' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'heic'],
        'text' => ['doc', 'docx', 'odt', 'rtf', 'txt', 'md'],
        'tabelle' => ['xls', 'xlsx', 'ods', 'csv'],
        'archiv' => ['zip', 'rar', '7z', 'tar', 'gz'],
    ];

    /**
     * Die Groesse in einer Form, die man im Vorbeigehen liest: "1,4 MB" statt
     * 1468006. Ohne gespeicherte Groesse (Bestandsdatei, deren Datei fehlt)
     * bleibt die Angabe leer statt "0 B" - das waere eine Falschaussage.
     */
    public function groesseLesbar(): ?string
    {
        if ($this->size === null) {
            return null;
        }

        $einheiten = ['B', 'KB', 'MB', 'GB'];
        $wert = (float) $this->size;
        $i = 0;

        while ($wert >= 1024 && $i < count($einheiten) - 1) {
            $wert /= 1024;
            $i++;
        }

        return number_format($wert, $i === 0 ? 0 : 1, ',', '.').' '.$einheiten[$i];
    }

    /**
     * Grobe Einordnung der Datei - "pdf", "bild", "text", "tabelle", "archiv"
     * oder "datei". Genauer muss es nicht sein: Es geht darum, eine Liste auf
     * einen Blick zu ueberfliegen, nicht um eine Dateitypenkunde.
     */
    public function art(): string
    {
        $endung = strtolower((string) $this->extension);

        foreach (self::ARTEN as $art => $endungen) {
            if (in_array($endung, $endungen, true)) {
                return $art;
            }
        }

        return 'datei';
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
