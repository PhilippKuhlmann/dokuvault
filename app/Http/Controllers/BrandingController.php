<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eigener Name und eigene Logos der Installation.
 *
 * Drei Logos statt eines: Das auf der Anmeldeseite darf gross und breit sein,
 * das in der Kopfzeile muss neben den Namen passen, und ein Favicon ist
 * quadratisch und winzig. In der Praxis sind das verschiedene Dateien - wer
 * nur eine hat, laedt sie eben dreimal hoch.
 *
 * Die Dateien liegen auf der local-Disk und gehen ueber logo() heraus, nicht
 * ueber public/storage: Die App legt alle Dateien privat ab und reicht sie
 * durch einen Controller - ein Symlink waere der einzige Sonderweg und
 * muesste auf jedem Server zusaetzlich eingerichtet werden.
 */
class BrandingController extends Controller
{
    /**
     * Erlaubte Bildformate.
     *
     * Ohne SVG: Eine SVG-Datei darf Skript enthalten, und ausgeliefert von
     * derselben Herkunft waere das ausfuehrbarer Code auf jeder Seite - in
     * einer Dokumentation, in der Kennwoerter stehen. PNG mit transparentem
     * Hintergrund tut dasselbe fuer ein Logo.
     */
    private const FORMATE = ['png', 'jpg', 'jpeg', 'webp'];

    public function index()
    {
        return view('admin.setting.allgemein', [
            'name' => Setting::wert(Setting::APP_NAME),
            'standardName' => config('app.name'),
            'stellen' => $this->stellen(),
            'formate' => self::FORMATE,
        ]);
    }

    public function update(Request $request)
    {
        $regeln = ['app_name' => ['nullable', 'string', 'max:60']];

        foreach (Setting::LOGO_STELLEN as $stelle) {
            $regeln['logo_'.$stelle] = ['nullable', 'image', 'mimes:'.implode(',', self::FORMATE), 'max:512'];
            $regeln['entfernen_'.$stelle] = ['nullable', 'boolean'];
        }

        $daten = $request->validate($regeln, [], $this->bezeichnungen());

        // Kein "required": Ein leeres Feld heisst "wieder den Namen aus der
        // .env nehmen", nicht "Name loeschen".
        Setting::setzen(Setting::APP_NAME, trim((string) ($daten['app_name'] ?? '')) ?: null);

        foreach (Setting::LOGO_STELLEN as $stelle) {
            if ($request->boolean('entfernen_'.$stelle)) {
                $this->altesLoeschen($stelle);
                Setting::setzen(Setting::logoSchluessel($stelle), null);
            }

            if ($request->hasFile('logo_'.$stelle)) {
                // Erst das alte weg, sonst bleibt bei jedem Wechsel eine Datei
                // liegen, die niemand mehr findet.
                $this->altesLoeschen($stelle);

                $pfad = $request->file('logo_'.$stelle)->store('branding', 'local');
                Setting::setzen(Setting::logoSchluessel($stelle), $pfad);
            }
        }

        return redirect(route('admin.allgemein.index'))
            ->with('success', __('Einstellungen gespeichert.'));
    }

    /**
     * Ein Logo ausliefern - ohne Anmeldung, es steht auch auf der Anmeldeseite.
     *
     * Die Stelle kommt aus der Whitelist, nie roh aus der Adresse: Sonst waere
     * der Einstellungs-Schluessel von aussen bestimmbar.
     *
     * nosniff und eine feste Content-Type-Angabe: Der Browser soll die Datei
     * als Bild behandeln und nicht selbst raten, was sie sein koennte.
     */
    public function logo(string $stelle)
    {
        abort_unless(in_array($stelle, Setting::LOGO_STELLEN, true), 404);

        $pfad = Setting::logoPfad($stelle);

        abort_if($pfad === null || ! Storage::disk('local')->exists($pfad), 404);

        return response(Storage::disk('local')->get($pfad), Response::HTTP_OK, [
            'Content-Type' => Storage::disk('local')->mimeType($pfad),
            'X-Content-Type-Options' => 'nosniff',
            // Das Logo aendert sich selten, steht aber auf jeder Seite.
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** Die drei Stellen mit Beschriftung, Hinweis und ob dort schon etwas liegt. */
    private function stellen(): array
    {
        // Aus der Konfiguration, nicht hier: Dort findet der
        // Uebersetzungs-Test (LocaleTest) die Beschriftungen.
        $texte = config('custom.branding_logos');

        return collect(Setting::LOGO_STELLEN)->map(fn ($stelle) => [
            'stelle' => $stelle,
            'label' => $texte[$stelle][0],
            'hinweis' => $texte[$stelle][1],
            'vorhanden' => Setting::logoPfad($stelle) !== null,
        ])->all();
    }

    private function bezeichnungen(): array
    {
        $namen = ['app_name' => __('Name')];

        foreach ($this->stellen() as $s) {
            $namen['logo_'.$s['stelle']] = __('Logo').' '.__($s['label']);
        }

        return $namen;
    }

    private function altesLoeschen(string $stelle): void
    {
        $alt = Setting::logoPfad($stelle);

        if ($alt !== null && Storage::disk('local')->exists($alt)) {
            Storage::disk('local')->delete($alt);
        }
    }
}
