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
     * The size in a form one reads in passing: "1,4 MB" instead of 1468006.
     * Without a stored size (a legacy file whose file is missing) the figure
     * stays empty instead of "0 B" - that would be a false statement.
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
     * Rough classification of the file - "pdf", "bild", "text", "tabelle",
     * "archiv" or "datei". It does not need to be more precise: the point is
     * to skim a list at a glance, not to teach file types.
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
     * The extensions of one kind - for the list filter.
     *
     * "datei" is the remainder: everything in none of the lists. There is no
     * extension list for that, so the query has to express it as "not in all
     * anderen" formulieren.
     */
    public static function extensionsForType(string $art): array
    {
        return config('custom.file_arten.'.$art.'.1', []);
    }

    /** Every extension assigned to a named kind. */
    public static function bekannteEndungen(): array
    {
        return collect(config('custom.file_arten'))->flatMap(fn ($a) => $a[1])->all();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
