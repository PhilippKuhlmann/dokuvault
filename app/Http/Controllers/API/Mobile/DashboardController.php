<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\Kundenuebersicht;
use App\Support\MobilObjekte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Das Kunden-Dashboard fuer die App: Fristen, Standorte, Ansprechpartner,
 * Agenten, die letzten Aenderungen.
 *
 * Gerechnet wird nichts hier - Kundenuebersicht bedient auch die Seite im
 * Browser. Zwei Rechnungen fuer dieselbe Frist waeren zwei Wahrheiten, und
 * der Techniker vor Ort haette die schlechtere.
 *
 * Statt der ?highlight=-Verweise des Browsers steht an jeder Zeile "typ" und
 * "id": damit oeffnet die App das Objekt. "typ" ist null, wo sie es nicht
 * kann - eine Zeile, die ins Leere fuehrt, ist schlimmer als eine ohne Weg.
 */
class DashboardController extends Controller
{
    public function __construct(private MobilObjekte $objekte) {}

    public function zeigen(Request $request, Customer $customer): JsonResponse
    {
        $nutzer = $request->user();
        $uebersicht = new Kundenuebersicht($customer, $nutzer);

        return response()->json([
            ...$this->objekte->kunde($customer),
            'fristen' => [
                'lizenzen' => $this->fristen($uebersicht->lizenzen()),
                'zertifikate' => $this->fristen($uebersicht->zertifikate()),
                'garantien' => $this->fristen($uebersicht->garantien()),
                'support_ende' => $this->fristen($uebersicht->supportEnde()),
            ],
            'standorte' => $uebersicht->standorte()
                ->map(fn ($standort) => $this->objekte->kurz($standort, 'site'))
                ->values(),
            // Mit Telefon und Mail: im Browser steht beides auf der Karte,
            // und auf dem Telefon ist eine Nummer ein Anruf.
            'ansprechpartner' => $uebersicht->ansprechpartner()
                ->map(fn ($person) => [
                    ...$this->objekte->kurz($person, 'contactperson'),
                    'rolle' => $person->role,
                    'telefon' => $person->phone,
                    'mail' => $person->mail,
                ])
                ->values(),
            // null, wenn der Kunde weder Agenten noch Token hat oder der
            // Angemeldete sie nicht verwaltet - dann zeigt die App keine
            // Karte, keine leere.
            'agenten' => $uebersicht->agentenWarnungen()?->values(),
            'aenderungen' => $uebersicht->letzteAenderungen()
                ->map(fn ($aenderung) => [
                    'name' => $aenderung['name'],
                    'art' => $aenderung['art'],
                    'ereignis' => $aenderung['ereignis'],
                    'wer' => $aenderung['wer'],
                    'wann' => $aenderung['wann']?->toIso8601String(),
                    'typ' => $this->oeffenbar($aenderung['typ']),
                    'id' => $aenderung['id'],
                ])
                ->values(),
        ]);
    }

    /** Eine Fristen-Kachel ohne die Browser-Verweise. */
    private function fristen(Collection $zeilen): Collection
    {
        return $zeilen->map(fn ($zeile) => [
            'name' => $zeile['name'],
            'art' => $zeile['art'],
            'datum' => $zeile['datum']->toIso8601String(),
            'tage' => $zeile['tage'],
            'typ' => $this->oeffenbar($zeile['typ']),
            'id' => $zeile['id'],
        ])->values();
    }

    /**
     * Der Typ, wenn die App ihn oeffnen kann - sonst null.
     *
     * Die Fristen sammeln ueber config('custom.trashables'), die App kennt
     * MobilObjekte::typen(). Das ist nicht dasselbe: Eine Checkliste steht im
     * Papierkorb, hat in der App aber keine Ansicht.
     */
    private function oeffenbar(?string $typ): ?string
    {
        return $typ !== null && array_key_exists($typ, $this->objekte->typen()) ? $typ : null;
    }
}
