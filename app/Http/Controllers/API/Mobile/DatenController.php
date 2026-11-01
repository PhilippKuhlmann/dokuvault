<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\CredentialLink;
use App\Models\Customer;
use App\Models\Site;
use App\Support\MobileObjects;
use App\Support\PasswordReveal;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Looking things up from the iOS app: customers, their objects, search, passwords.
 *
 * Read only. Every response goes through the same permissions as the page in
 * the browser, and a customer user sees only their customer - the ids come
 * from the phone here and not from a locked Livewire property.
 */
class DatenController extends Controller
{
    public function __construct(private MobileObjects $objekte) {}

    public function kunden(Request $request): JsonResponse
    {
        $nutzer = $request->user();
        $suche = (string) $request->query('search');

        $kunden = Customer::query()
            ->when($nutzer->hasCustomer(), fn ($abfrage) => $abfrage->whereKey($nutzer->customer_id))
            ->when($suche !== '', fn ($abfrage) => $abfrage->whereEnthaelt('name', $suche))
            ->orderBy('name')
            ->limit(MobileObjects::LISTE_HOECHSTENS)
            ->get();

        return response()->json($kunden->map(fn ($kunde) => $this->objekte->kunde($kunde))->values());
    }

    /** The customer with the object kinds documented at them. */
    public function kunde(Request $request, Customer $customer): JsonResponse
    {
        $typen = collect($this->objekte->visibleKinds($request->user()))
            ->map(fn ($typ, $slug) => [
                'type' => $slug,
                'title' => $this->objekte->titel($slug),
                'count' => $typ[0]::where('customer_id', $customer->id)->count(),
            ])
            ->filter(fn ($typ) => $typ['anzahl'] > 0)
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            ...$this->objekte->kunde($customer),
            'types' => $typen,
        ]);
    }

    /** Alle Objekte einer Art bei einem Kunden. */
    public function liste(Request $request, Customer $customer, string $typ): JsonResponse
    {
        $eintrag = $this->objekte->visibleKinds($request->user())[$typ] ?? abort(404);
        $klasse = $eintrag[0];

        $mitStandort = method_exists($klasse, 'site');
        $mitladen = $mitStandort ? ['customer', 'site'] : ['customer'];

        // The site filter of the sidebar, here as a query value. A
        // A site of another customer counts as no filter, not as an error -
        // just as in the browser, where a foreign site in the session is
        // skipped.
        $standort = (int) $request->query('site');
        $standort = $standort && $mitStandort && $customer->sites()->whereKey($standort)->exists()
            ? $standort
            : null;

        $objekte = $klasse::with($mitladen)
            ->where('customer_id', $customer->id)
            ->when($standort, fn ($abfrage) => $abfrage->where('site_id', $standort))
            ->limit(MobileObjects::LISTE_HOECHSTENS + 1)
            ->get();

        $zeilen = $objekte->take(MobileObjects::LISTE_HOECHSTENS)
            ->map(fn ($objekt) => $this->objekte->kurz($objekt, $typ))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            'type' => $typ,
            'title' => $this->objekte->titel($typ),
            'entries' => $zeilen,
            'more' => $objekte->count() > MobileObjects::LISTE_HOECHSTENS,
            'may_create' => $this->objekte->darfAnlegen($request->user(), $typ),
            // The choices for the site filter. Only where the kind carries
            // an einem Standort haengt - sonst gaebe es nichts zu filtern.
            'sites' => $mitStandort
                ? Site::where('customer_id', $customer->id)->orderBy('name')->get(['id', 'name'])
                : null,
        ]);
    }

    /** Dieselbe Suche wie im Browser, siehe GlobaleSuche. */
    public function suche(Request $request, Search $suche): JsonResponse
    {
        $gruppen = $suche->groups($request->user(), (string) $request->query('q'))
            ->reject(fn ($gruppe) => $gruppe['detailseite'])
            ->map(fn ($gruppe) => [
                'type' => $gruppe['typ'],
                'title' => __($gruppe['label']),
                'more' => $gruppe['weitere'],
                'hits' => $gruppe['results']
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

    /** The password behind a link - one at a time, throttled, logged. */
    public function kennwort(Request $request, CredentialLink $link, PasswordReveal $abruf): JsonResponse
    {
        $wert = $abruf->ausVerknuepfung($link, $request->user());

        return $wert === null
            ? response()->json(['message' => __('Kein Kennwort hinterlegt.')], 404)
            : response()->json(['value' => $wert]);
    }

    /** Ein Geheimnis direkt am Objekt (BMC-Passwort, PIN ...). */
    public function geheimnis(Request $request, string $typ, int $id, string $feld, PasswordReveal $abruf): JsonResponse
    {
        $objekt = $this->objekte->finden($typ, $id, $request->user());

        $wert = $abruf->fromField($objekt::class, $objekt->getKey(), $feld, $request->user());

        return $wert === null
            ? response()->json(['message' => __('Kein Kennwort hinterlegt.')], 404)
            : response()->json(['value' => $wert]);
    }
}
