<?php

namespace App\Jobs;

use App\Models\PdfExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
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
        $export = PdfExport::with('customer', 'user')->find($this->exportId);

        if (! $export || ! $export->customer) {
            return;
        }

        $export->update(['status' => PdfExport::LAEUFT]);

        // The need grows with the customer: 370 MB were measured, the next
        // larger customer needs more. In the background process a high limit
        // bothers nobody - unlike in the web server, where it would apply to
        // every request.
        ini_set('memory_limit', '1G');

        // The rack front views are SVG. DomPDF renders them only from an
        // image file inside its chroot, hence a short-lived folder - which
        // also goes away when rendering fails.
        $svgDir = storage_path('app/pdf-svg/'.Str::uuid());
        File::ensureDirectoryExists($svgDir);

        // Whoever ordered the export sees only what they may see in the
        // browser: every section checks this user's viewAny right (see
        // components/pdf/section). Without an owner, sections stay empty.
        View::share('pdfOwner', $export->user);

        try {
            $pdf = Pdf::loadView('pdf.customer', [
                'customer' => $export->customer,
                'svgDir' => $svgDir,
            ])->output();

            $path = 'pdf-exports/'.$export->customer_id.'/'
                .now()->format('Y-m-d_His').'_'.$export->id.'_dokumentation.pdf';

            Storage::disk('local')->put($path, $pdf);

            $export->update([
                'status' => PdfExport::FERTIG,
                'path' => $path,
                'size' => strlen($pdf),
                'finished_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $e) {
            // The message ends up on the page - so shortened and without a
            // stack trace, that belongs in the log.
            $export->update([
                'status' => PdfExport::FEHLER,
                'error' => Str::limit($e->getMessage(), 300),
                'finished_at' => now(),
            ]);

            throw $e;
        } finally {
            File::deleteDirectory($svgDir);
            View::share('pdfOwner', null);
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
