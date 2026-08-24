<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Liefert die eigenen Logos der Installation aus.
 *
 * Gepflegt werden sie in App\Livewire\AdminAllgemein - hier geht es nur
 * darum, die abgelegte Datei herauszugeben.
 *
 * Die Dateien liegen auf der local-Disk und gehen ueber logo() heraus, nicht
 * ueber public/storage: Die App legt alle Dateien privat ab und reicht sie
 * durch einen Controller - ein Symlink waere der einzige Sonderweg und
 * muesste auf jedem Server zusaetzlich eingerichtet werden.
 */
class BrandingController extends Controller
{
    /**
     * Ein Logo ausliefern - ohne Anmeldung, es steht auch auf der Anmeldeseite.
     *
     * Die Stelle kommt aus der Whitelist, nie roh aus der Adresse: Sonst waere
     * der Einstellungs-Schluessel von aussen bestimmbar. Der Parameter heisst
     * englisch wie die Adresse - die Werte selbst (login, header, favicon)
     * waren es schon.
     *
     * nosniff und eine feste Content-Type-Angabe: Der Browser soll die Datei
     * als Bild behandeln und nicht selbst raten, was sie sein koennte.
     */
    public function logo(string $placement)
    {
        abort_unless(in_array($placement, Setting::LOGO_STELLEN, true), 404);

        $pfad = Setting::logoPfad($placement);

        abort_if($pfad === null || ! Storage::disk('local')->exists($pfad), 404);

        return response(Storage::disk('local')->get($pfad), Response::HTTP_OK, [
            'Content-Type' => Storage::disk('local')->mimeType($pfad),
            'X-Content-Type-Options' => 'nosniff',
            // Das Logo aendert sich selten, steht aber auf jeder Seite.
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
