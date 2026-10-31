<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Jobs\KundenPdfErzeugen;
use App\Models\Customer;
use App\Models\PdfExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Die Dokumentation eines Kunden als PDF - bestellt, nicht sofort gerendert.
 *
 * Wie im Browser: Der Klick legt einen Auftrag an, den der Scheduler
 * abarbeitet, und die Seite fragt den Stand ab. Gemessen an zwei Kunden
 * brauchen 40 Server, 90 VMs und 160 Computer 370 MB und 15 Sekunden - das
 * ueberlebt kein Request.
 *
 * Je Nutzer, nicht je Kunde: Das PDF enthaelt alle Zugangsdaten, abholen
 * darf es nur, wer es bestellt hat.
 */
class PdfController extends Controller
{
    public function stand(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('create_pdf');

        $auftrag = $this->auftrag($request, $customer);

        return response()->json(['auftrag' => $this->form($auftrag)]);
    }

    /**
     * Einen Auftrag anlegen - oder den laufenden zurueckgeben.
     *
     * Kein zweiter, solange einer laeuft: Wer zweimal tippt, soll nicht
     * zweimal denselben Berg Arbeit ausloesen.
     */
    public function bestellen(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('create_pdf');

        $auftrag = $this->auftrag($request, $customer);

        if (! $auftrag?->laeuftNoch()) {
            $auftrag = PdfExport::create([
                'customer_id' => $customer->id,
                'user_id' => $request->user()->id,
                'status' => PdfExport::OFFEN,
            ]);

            KundenPdfErzeugen::dispatch($auftrag->id);
        }

        return response()->json(['auftrag' => $this->form($auftrag)]);
    }

    /**
     * Die fertige Datei.
     *
     * Das PDF traegt jedes Kennwort des Kunden - sein Abruf gehoert ins
     * Protokoll wie ein einzeln angesehenes Kennwort.
     */
    public function laden(Request $request, Customer $customer, PdfExport $pdfExport): StreamedResponse
    {
        Gate::authorize('create_pdf');

        // Der Auftrag eines anderen Kunden ist nicht dieser - und der
        // eines anderen Nutzers geht niemanden etwas an, auch nicht, wer
        // denselben Kunden sehen darf.
        abort_if($pdfExport->customer_id !== $customer->id, 404);
        abort_if($pdfExport->user_id !== $request->user()->id, 403);
        abort_unless($pdfExport->istFertig() && $pdfExport->path, 404);

        // Der Pfad kommt aus dem Auftrag, den der Job geschrieben hat, nicht
        // aus einem Formular - trotzdem: Er muss im Ordner dieses Kunden
        // liegen und darf nicht herausfuehren. Ein Pfad, der das nicht tut,
        // ist kein Pfad dieses Auftrags.
        $ordner = 'pdf-exports/'.$customer->id.'/';
        abort_unless(
            str_starts_with($pdfExport->path, $ordner) && ! str_contains($pdfExport->path, '..'),
            404,
        );

        abort_unless(Storage::disk('local')->exists($pdfExport->path), 410);

        activity()
            ->event('pdf_heruntergeladen')
            ->performedOn($customer)
            ->causedBy($request->user())
            ->withProperties(['objekt' => $customer->name, 'attributes' => ['IP' => $request->ip()]])
            ->log('PDF mit Zugangsdaten heruntergeladen');

        return Storage::disk('local')->download(
            $pdfExport->path,
            Str::slug($customer->name).'-dokumentation.pdf'
        );
    }

    /** Der letzte Auftrag dieses Nutzers zu diesem Kunden. */
    private function auftrag(Request $request, Customer $customer): ?PdfExport
    {
        return PdfExport::where('customer_id', $customer->id)
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();
    }

    /** @return array<string, mixed>|null */
    private function form(?PdfExport $auftrag): ?array
    {
        if ($auftrag === null) {
            return null;
        }

        return [
            'id' => $auftrag->id,
            // "offen", "laeuft", "fertig", "fehler"
            'status' => $auftrag->status,
            'laeuft_noch' => $auftrag->laeuftNoch(),
            // Niemand arbeitet die Warteschlange ab - dann hat Nachfragen
            // keinen Zweck mehr.
            'haengt' => $auftrag->haengt(),
            'fertig' => $auftrag->istFertig() && $auftrag->path !== null,
            'groesse' => $auftrag->groesseLesbar(),
            'angelegt' => $auftrag->created_at?->toIso8601String(),
            'fertig_am' => $auftrag->finished_at?->toIso8601String(),
            'meldung' => $auftrag->error,
        ];
    }
}
