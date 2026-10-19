<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\CredentialLink;
use App\Models\Customer;
use App\Support\GlobaleSuche;
use App\Support\Kennwortabruf;
use App\Support\MobilObjekte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nachschlagen aus der iOS-App: Kunden, ihre Objekte, Suche, Kennwoerter.
 *
 * Nur lesen. Jede Antwort geht durch dieselben Rechte wie die Seite im
 * Browser, und ein Kundennutzer sieht nur seinen Kunden - die Ids kommen hier
 * vom Telefon und nicht aus einer gesperrten Livewire-Eigenschaft.
 */
class DatenController extends Controller
{
    public function __construct(private MobilObjekte $objekte) {}

    public function kunden(Request $request): JsonResponse
    {
        $nutzer = $request->user();
        $suche = (string) $request->query('suche');

        $kunden = Customer::query()
            ->when($nutzer->hasCustomer(), fn ($abfrage) => $abfrage->whereKey($nutzer->customer_id))
            ->when($suche !== '', fn ($abfrage) => $abfrage->whereEnthaelt('name', $suche))
            ->orderBy('name')
            ->limit(MobilObjekte::LISTE_HOECHSTENS)
            ->get();

        return response()->json($kunden->map(fn ($kunde) => $this->objekte->kunde($kunde))->values());
    }

    /** Der Kunde mit den Objektarten, die bei ihm dokumentiert sind. */
    public function kunde(Request $request, Customer $customer): JsonResponse
    {
        $typen = collect($this->objekte->sichtbareTypen($request->user()))
            ->map(fn ($typ, $slug) => [
                'typ' => $slug,
                'titel' => $this->objekte->titel($slug),
                'anzahl' => $typ[0]::where('customer_id', $customer->id)->count(),
            ])
            ->filter(fn ($typ) => $typ['anzahl'] > 0)
            ->sortBy('titel', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            ...$this->objekte->kunde($customer),
            'typen' => $typen,
        ]);
    }

    /** Alle Objekte einer Art bei einem Kunden. */
    public function liste(Request $request, Customer $customer, string $typ): JsonResponse
    {
        $eintrag = $this->objekte->sichtbareTypen($request->user())[$typ] ?? abort(404);
        $klasse = $eintrag[0];

        $mitladen = method_exists($klasse, 'site') ? ['customer', 'site'] : ['customer'];

        $objekte = $klasse::with($mitladen)
            ->where('customer_id', $customer->id)
            ->limit(MobilObjekte::LISTE_HOECHSTENS + 1)
            ->get();

        $zeilen = $objekte->take(MobilObjekte::LISTE_HOECHSTENS)
            ->map(fn ($objekt) => $this->objekte->kurz($objekt, $typ))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            'typ' => $typ,
            'titel' => $this->objekte->titel($typ),
            'eintraege' => $zeilen,
            'weitere' => $objekte->count() > MobilObjekte::LISTE_HOECHSTENS,
        ]);
    }

    /** Dieselbe Suche wie im Browser, siehe GlobaleSuche. */
    public function suche(Request $request, GlobaleSuche $suche): JsonResponse
    {
        $gruppen = $suche->gruppen($request->user(), (string) $request->query('q'))
            ->reject(fn ($gruppe) => $gruppe['detailseite'])
            ->map(fn ($gruppe) => [
                'typ' => $gruppe['typ'],
                'titel' => __($gruppe['label']),
                'weitere' => $gruppe['weitere'],
                'treffer' => $gruppe['results']
                    ->map(fn ($objekt) => $this->objekte->kurz($objekt, $gruppe['typ']))
                    ->values(),
            ])
            ->values();

        return response()->json($gruppen);
    }

    public function objekt(Request $request, string $typ, int $id): JsonResponse
    {
        $objekt = $this->objekte->finden($typ, $id, $request->user());

        return response()->json($this->objekte->detail($objekt, $typ, $request->user()));
    }

    /** Das Kennwort hinter einer Verknuepfung - einzeln, gebremst, protokolliert. */
    public function kennwort(Request $request, CredentialLink $link, Kennwortabruf $abruf): JsonResponse
    {
        $wert = $abruf->ausVerknuepfung($link, $request->user());

        return $wert === null
            ? response()->json(['message' => __('Kein Kennwort hinterlegt.')], 404)
            : response()->json(['wert' => $wert]);
    }

    /** Ein Geheimnis direkt am Objekt (BMC-Passwort, PIN ...). */
    public function geheimnis(Request $request, string $typ, int $id, string $feld, Kennwortabruf $abruf): JsonResponse
    {
        $objekt = $this->objekte->finden($typ, $id, $request->user());

        $wert = $abruf->ausFeld($objekt::class, $objekt->getKey(), $feld, $request->user());

        return $wert === null
            ? response()->json(['message' => __('Kein Kennwort hinterlegt.')], 404)
            : response()->json(['wert' => $wert]);
    }
}
