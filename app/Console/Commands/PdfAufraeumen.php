<?php

namespace App\Console\Commands;

use App\Models\PdfExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Alte PDF-Ausgaben loeschen.
 *
 * Die Dateien enthalten alle Zugangsdaten eines Kunden im Klartext. Nach dem
 * Abholen haben sie keinen Grund, im Dateisystem liegen zu bleiben - und wer
 * sie noch braucht, gibt den Auftrag neu.
 */
class PdfAufraeumen extends Command
{
    protected $signature = 'pdf:aufraeumen';

    protected $description = 'Loescht PDF-Ausgaben, die aelter als die Aufbewahrungszeit sind';

    public function handle(): int
    {
        $grenze = now()->subHours(PdfExport::AUFBEWAHRUNG_STUNDEN);
        $anzahl = 0;

        PdfExport::where('created_at', '<', $grenze)
            ->orderBy('id')
            ->chunkById(200, function ($auftraege) use (&$anzahl) {
                foreach ($auftraege as $auftrag) {
                    if ($auftrag->path) {
                        Storage::disk('local')->delete($auftrag->path);
                    }

                    $auftrag->delete();
                    $anzahl++;
                }
            });

        $this->info($anzahl.' PDF-Ausgaben aufgeraeumt.');

        return self::SUCCESS;
    }
}
