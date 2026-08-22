<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Den Objektnamen in bestehende Protokolleinträge nachtragen.
 *
 * Das Protokoll speichert nur die geänderten Felder. Wer an einer Domain bloß
 * den Registrar ändert, hinterließ damit einen Eintrag ohne Namen, und in der
 * Übersicht stand "Domain #1". Gemessen an der Entwicklungsdatenbank: 27 von
 * 50 sichtbaren Zeilen - vor allem IP-Adressen, Zugangsdaten-Verknüpfungen und
 * Rack-Einbauten, die gar keine name-Spalte haben.
 *
 * Neue Einträge bringen den Namen jetzt selbst mit. Für die alten wird er hier
 * einmal nachgeschlagen - beim Anzeigen wäre das der falsche Ort: Ein Eintrag
 * überlebt sein Objekt, und ein Verweis auf eine entfernte Klasse bricht dort
 * die ganze Seite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        $ergaenzt = 0;
        $offen = 0;

        DB::table('activity_log')
            ->whereNotNull('subject_type')
            ->orderBy('id')
            ->chunkById(300, function ($eintraege) use (&$ergaenzt, &$offen) {
                foreach ($eintraege as $eintrag) {
                    $eigenschaften = json_decode($eintrag->properties ?? '', true);

                    if (! is_array($eigenschaften) || filled($eigenschaften['objekt'] ?? null)) {
                        continue;
                    }

                    // Steht der Name schon in den geänderten Feldern, genügt der.
                    if (filled($eigenschaften['attributes']['name'] ?? null)) {
                        continue;
                    }

                    $name = $this->nameHolen($eintrag->subject_type, $eintrag->subject_id);

                    if (blank($name)) {
                        $offen++;

                        continue;
                    }

                    $eigenschaften['objekt'] = $name;

                    DB::table('activity_log')->where('id', $eintrag->id)->update([
                        'properties' => json_encode($eigenschaften, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);

                    $ergaenzt++;
                }
            });

        info("Protokoll: {$ergaenzt} Einträge um den Objektnamen ergänzt, {$offen} ohne Fundstelle.");
    }

    /**
     * Der Name, wenn Klasse und Objekt noch da sind.
     *
     * Beides kann fehlen: Eine Klasse wird entfernt (die Securepoint UTM war
     * so ein Fall), ein Objekt endgültig gelöscht. Dann bleibt der Eintrag bei
     * seiner Nummer - das ist ehrlicher als ein erfundener Name.
     */
    private function nameHolen(string $klasse, $id): ?string
    {
        if (! class_exists($klasse)) {
            return null;
        }

        try {
            $objekt = $klasse::find($id);

            return $objekt && method_exists($objekt, 'protokollName')
                ? $objekt->protokollName()
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Ein nachgetragener Name ist kein Schaden - ihn zu entfernen brächte nichts.
     */
    public function down(): void {}
};
