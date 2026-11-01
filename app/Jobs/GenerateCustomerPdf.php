<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\PdfExport;
use Barryvdh\DomPDF\Facade\Pdf;
use DOMElement;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Dompdf\Frame;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Throwable;

/**
 * Generates the customer documentation as a PDF - outside the request.
 *
 * Measured on two customers: 26 servers, 46 VMs and 53 computers need 136 MB
 * and 2 seconds; 40 servers, 90 VMs and 160 computers already 370 MB and 15
 * seconds. In the request that meant first an error page (memory), then a
 * race against the time limit of web server and PHP. Neither matters here.
 */
class GenerateCustomerPdf implements ShouldQueue
{
    use Queueable;

    /** Large customers need minutes, not seconds. */
    public int $timeout = 600;

    /**
     * No second attempt: if it fails, it is the data or the memory, and a
     * retry brought the same result - only later. The error stands in the job
     * and thereby on the page.
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
            // Rendered twice. Which page a section begins on is only known
            // once everything before it has been laid out, so the first pass
            // runs without a contents and the second one carries the numbers
            // it found.
            //
            // Those numbers do not count the front matter: page 1 is the one
            // the first section starts on, which is also what makes this
            // work in two passes. The contents pushes cover and sections
            // apart by its own length, but it pushes the front matter along
            // with it - so a section's number does not move. The loop checks
            // that anyway and renders once more if it ever did.
            $toc = [];
            $pdf = '';

            for ($lauf = 0; $lauf < 3; $lauf++) {
                [$pdf, $seiten] = $this->render($export->customer, $svgDir, $toc);

                $neu = [];

                foreach ($seiten as $titel => $seite) {
                    $neu[] = ['title' => $titel, 'page' => $seite - $this->vorspann($seiten)];
                }

                // $pdf was rendered from $toc, and $neu says where the
                // sections really are. Equal means the sheet is telling the
                // truth about itself.
                if ($neu === $toc) {
                    break;
                }

                $toc = $neu;
            }

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

    /**
     * Renders the sheet once and says where its sections ended up.
     *
     * While rendering, DomPDF reports every frame it puts on the page. The
     * section headings carry data-toc, so their page number can be read off
     * the canvas - there is no other way round it, a heading's page is only
     * decided once everything before it has been laid out.
     *
     * @param  list<array{title: string, page: int}>  $toc
     * @return array{0: string, 1: array<string, int>}
     */
    private function render(Customer $customer, string $svgDir, array $toc): array
    {
        $seiten = [];

        $pdf = Pdf::loadView('pdf.customer', [
            'customer' => $customer,
            'svgDir' => $svgDir,
            'toc' => $toc,
        ]);

        $pdf->getDomPDF()->setCallbacks([
            [
                'event' => 'begin_frame',
                'f' => function (Frame $frame, Canvas $canvas) use (&$seiten) {
                    $node = $frame->get_node();

                    if ($node instanceof DOMElement && $node->hasAttribute('data-toc')) {
                        // The first appearance counts: a heading that is split
                        // across a page break would otherwise report the
                        // second page.
                        $seiten[$node->getAttribute('data-toc')] ??= $canvas->get_page_number();
                    }
                },
            ],
            [
                'event' => 'end_document',
                'f' => function (int $nr, int $gesamt, Canvas $canvas, FontMetrics $fontMetrics) use (&$seiten, $customer) {
                    $this->kopfUndFuss($nr, $gesamt, $canvas, $fontMetrics, $seiten, $customer->name, $this->vorspann($seiten));
                },
            ],
        ]);

        return [$pdf->output(), $seiten];
    }

    /**
     * Pages before the first section: cover and table of contents.
     *
     * They carry neither head nor foot and do not count towards the page
     * number - page 1 is the one the first section begins on.
     *
     * @param  array<string, int>  $seiten
     */
    private function vorspann(array $seiten): int
    {
        return $seiten === [] ? 1 : min($seiten) - 1;
    }

    /**
     * Draws the running head and the foot of one page.
     *
     * Both sit outside the text flow, on the canvas - which is why the body
     * keeps a wider margin at the top and bottom than at the sides. The head
     * names the section the page belongs to: with nine servers over three
     * pages, the heading alone is on the first of them.
     *
     * @param  array<string, int>  $seiten  Abschnitt => Seite, in Reihenfolge des Blattes
     */
    private function kopfUndFuss(int $nr, int $gesamt, Canvas $canvas, FontMetrics $fontMetrics, array $seiten, string $kunde, int $vorspann): void
    {
        if ($nr <= $vorspann) {
            return;
        }

        $schrift = $fontMetrics->getFont('Space Grotesk', 'normal') ?: $fontMetrics->getFont('sans-serif');
        $breite = $canvas->get_width();
        $hoehe = $canvas->get_height();
        $rand = 28.35;                  // 10mm, derselbe Seitenrand wie im Blatt
        $grau = [0.61, 0.64, 0.69];     // gray-400
        $linie = [0.90, 0.91, 0.92];    // gray-200
        $groesse = 8.5;

        // What the head names is what stands at the TOP of the page, where
        // the eye lands: the last section that began before it. A section
        // beginning halfway down this page does not count - the reader is
        // still looking at the one above. Only a page whose first section
        // starts on it takes that one.
        $abschnitt = '';

        foreach ($seiten as $titel => $seite) {
            if ($seite < $nr) {
                $abschnitt = $titel;
            }
        }

        if ($abschnitt === '') {
            $abschnitt = (string) array_search($nr, $seiten, true);
        }

        if ($abschnitt !== '') {
            $canvas->text($rand, $rand + 2, $abschnitt, $schrift, $groesse, $grau);
        }

        $canvas->line($rand, $rand + 16, $breite - $rand, $rand + 16, $linie, 0.5);

        $fuss = $hoehe - $rand - 4;
        $canvas->line($rand, $fuss - 8, $breite - $rand, $fuss - 8, $linie, 0.5);
        $canvas->text($rand, $fuss, $kunde.' · '.__('Dokumentation'), $schrift, $groesse, $grau);

        $zahl = __('Seite :nr von :gesamt', ['nr' => $nr - $vorspann, 'gesamt' => $gesamt - $vorspann]);
        $canvas->text($breite - $rand - $fontMetrics->getTextWidth($zahl, $schrift, $groesse), $fuss, $zahl, $schrift, $groesse, $grau);
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
