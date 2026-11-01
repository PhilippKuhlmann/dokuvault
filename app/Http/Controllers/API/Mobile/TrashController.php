<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\MobileObjects;
use App\Support\Trash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The trash in the app: see what was deleted, and bring it back.
 *
 * Worked out in App\Support\Trash, the same class that fills the page in the
 * browser - including the cap of a hundred entries per kind and the question
 * of who may see what.
 */
class TrashController extends Controller
{
    public function __construct(private MobileObjects $objekte) {}

    public function index(Request $request, Customer $customer): JsonResponse
    {
        Gate::authorize('see_hidden');

        $papierkorb = new Trash($customer, $request->user());

        return response()->json([
            'eintraege' => $papierkorb->eintraege()->map(fn ($eintrag) => [
                ...$eintrag,
                'geloescht_am' => $eintrag['geloescht_am']?->toIso8601String(),
            ])->values(),
            // Which kinds hold more than the list shows - cutting silently
            // reads like "there is no more".
            'gekuerzt' => collect($papierkorb->gekuerzt())
                ->map(fn ($anzahl, $bezeichnung) => ['titel' => __($bezeichnung), 'anzahl' => $anzahl])
                ->values(),
        ]);
    }

    /**
     * Ein Objekt zurueckholen.
     *
     * The response names the object the way the app opens it - right after
     * bringing something back one usually wants to look. For a kind without a
     * view in the app only the name stands there.
     */
    public function restore(Request $request, Customer $customer, string $typ, int $id): JsonResponse
    {
        Gate::authorize('see_hidden');

        $objekt = (new Trash($customer, $request->user()))->restore($typ, $id);

        return response()->json([
            'objekt' => array_key_exists($typ, $this->objekte->typen())
                ? $this->objekte->kurz($objekt, $typ)
                : null,
        ]);
    }
}
