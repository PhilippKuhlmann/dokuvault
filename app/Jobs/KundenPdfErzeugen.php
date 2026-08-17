<?php

namespace App\Jobs;

use App\Models\PdfExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Erzeugt die Kundendokumentation als PDF - ausserhalb des Requests.
 *
 * Gemessen an zwei Kunden: 26 Server, 46 VMs und 53 Computer brauchen 136 MB
 * und 2 Sekunden; 40 Server, 90 VMs und 160 Computer schon 370 MB und 15
 * Sekunden. Im Request hiess das erst eine Fehlerseite (Speicher), dann ein
 * Rennen gegen das Zeitlimit von Webserver und PHP. Hier stoert beides nicht.
 */
class KundenPdfErzeugen implements ShouldQueue
{
    use Queueable;

    /** Grosse Kunden brauchen Minuten, nicht Sekunden. */
    public int $timeout = 600;

    /**
     * Kein zweiter Versuch: Schlaegt es fehl, liegt es an den Daten oder am
     * Speicher, und ein Wiederholen brachte dasselbe Ergebnis - nur spaeter.
     * Der Fehler steht im Auftrag und damit auf der Seite.
     */
    public int $tries = 1;

    public function __construct(public int $exportId) {}

    public function handle(): void
    {
        $export = PdfExport::with('customer')->find($this->exportId);

        if (! $export || ! $export->customer) {
            return;
        }

        $export->update(['status' => PdfExport::LAEUFT]);

        // Der Bedarf waechst mit dem Kunden: 370 MB waren gemessen, der
        // naechste groessere Kunde braucht mehr. Im Hintergrundprozess stoert
        // ein hohes Limit niemanden - anders als im Webserver, wo es fuer jede
        // Anfrage gelten wuerde.
        ini_set('memory_limit', '1G');

        // Die Rack-Frontansichten sind SVG. DomPDF rendert sie nur aus einer
        // Bilddatei innerhalb seines chroot, deshalb ein kurzlebiger Ordner -
        // der auch verschwindet, wenn das Rendern fehlschlaegt.
        $svgDir = storage_path('app/pdf-svg/'.Str::uuid());
        File::ensureDirectoryExists($svgDir);

        try {
            $pdf = Pdf::loadView('pdf.customer', [
                'customer' => $export->customer,
                'svgDir' => $svgDir,
            ])->output();

            $pfad = 'pdf-exports/'.$export->customer_id.'/'
                .now()->format('Y-m-d_His').'_dokumentation.pdf';

            Storage::disk('local')->put($pfad, $pdf);

            $export->update([
                'status' => PdfExport::FERTIG,
                'path' => $pfad,
                'size' => strlen($pdf),
                'finished_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $e) {
            // Die Meldung landet auf der Seite - deshalb gekuerzt und ohne
            // Stacktrace, der gehoert ins Log.
            $export->update([
                'status' => PdfExport::FEHLER,
                'error' => Str::limit($e->getMessage(), 300),
                'finished_at' => now(),
            ]);

            throw $e;
        } finally {
            File::deleteDirectory($svgDir);
        }
    }

    public function failed(Throwable $e): void
    {
        PdfExport::whereKey($this->exportId)
            ->where('status', '!=', PdfExport::FEHLER)
            ->update([
                'status' => PdfExport::FEHLER,
                'error' => Str::limit($e->getMessage(), 300),
                'finished_at' => now(),
            ]);
    }
}
