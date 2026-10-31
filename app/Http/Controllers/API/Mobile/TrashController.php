<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\MobilObjekte;
use App\Support\Papierkorb;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Der Papierkorb in der App: sehen, was geloescht wurde, und es zurueckholen.
 *
 * Gerechnet wird in App\Support\Papierkorb, derselben Klasse, die die Seite
 * im Browser fuellt - einschliesslich der Grenze von hundert Eintraegen je
 * Art und der Frage, wer was sehen darf.
 */
class TrashController extends Controller
{
    public function __construct(private MobilObjekte $objekte) {}

    public function index(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('see_hidden');

        $papierkorb = new Papierkorb($customer, $request->user());

        return response()->json([
            'eintraege' => $papierkorb->eintraege()->map(fn ($eintrag) => [
                ...$eintrag,
                'geloescht_am' => $eintrag['geloescht_am']?->toIso8601String(),
            ])->values(),
            // Welche Arten mehr haben, als die Liste zeigt - ein stilles
            // Abschneiden liest sich wie "mehr ist nicht da".
            'gekuerzt' => collect($papierkorb->gekuerzt())
                ->map(fn ($anzahl, $bezeichnung) => ['titel' => __($bezeichnung), 'anzahl' => $anzahl])
                ->values(),
        ]);
    }

    /**
     * Ein Objekt zurueckholen.
     *
     * Die Antwort nennt das Objekt so, wie die App es oeffnet - direkt nach
     * dem Zurueckholen will man meist nachsehen. Bei einer Art ohne Ansicht
     * in der App steht dort nur der Name.
     */
    public function restore(Request $request, Customer $customer, string $typ, int $id): JsonResponse
    {
        Gate::authorize('see_hidden');

        $objekt = (new Papierkorb($customer, $request->user()))->wiederherstellen($typ, $id);

        return response()->json([
            'objekt' => array_key_exists($typ, $this->objekte->typen())
                ? $this->objekte->kurz($objekt, $typ)
                : null,
        ]);
    }
}
