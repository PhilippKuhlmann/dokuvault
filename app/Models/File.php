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

        foreach (config('custom.file_arten') as $art => [$beschriftung, $endungen]) {
            if (in_array($endung, $endungen, true)) {
                return $art;
            }
        }

        return 'datei';
    }

    /**
     * Die Endungen einer Art - fuer den Filter der Liste.
     *
     * "datei" ist der Rest: alles, was in keiner der Listen steht. Dafuer
     * gibt es keine Endungsliste, das muss die Abfrage als "nicht in allen
     * anderen" formulieren.
     */
    public static function endungenFuerArt(string $art): array
    {
        return config('custom.file_arten.'.$art.'.1', []);
    }

    /** Alle Endungen, die einer benannten Art zugeordnet sind. */
    public static function bekannteEndungen(): array
    {
        return collect(config('custom.file_arten'))->flatMap(fn ($a) => $a[1])->all();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
