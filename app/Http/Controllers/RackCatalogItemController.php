<?php

namespace App\Http\Controllers;

use App\Http\Requests\RackCatalogItemRequest;
use App\Models\RackCatalogItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pflege des Rack-Katalogs im Adminbereich. Die Route-Gruppe laeuft bereits
 * hinter der isAdmin-Middleware, deshalb keine zusaetzliche Autorisierung.
 *
 * Ausnahme ist image(): Das Bild steht in jeder Rack-Ansicht und muss deshalb
 * auch fuer Kunden erreichbar sein - die Route liegt ausserhalb der Gruppe.
 */
class RackCatalogItemController extends Controller
{
    public function index()
    {
        $rackCatalogItems = RackCatalogItem::ordered()->paginate(20);
        $rackCatalogItemsCount = RackCatalogItem::count();

        return view('admin.rackcatalogitem.index', compact('rackCatalogItems', 'rackCatalogItemsCount'));
    }

    public function create()
    {
        return view('admin.rackcatalogitem.create');
    }

    public function store(RackCatalogItemRequest $request)
    {
        $eintrag = RackCatalogItem::create($this->stammdaten($request));

        if ($request->hasFile('image')) {
            $eintrag->update(['image_path' => $this->bildAblegen($request)]);
        }

        return redirect(route('admin.rackcatalogitem.index'));
    }

    public function edit(RackCatalogItem $rackcatalogitem)
    {
        return view('admin.rackcatalogitem.edit', ['rackCatalogItem' => $rackcatalogitem]);
    }

    public function update(RackCatalogItem $rackcatalogitem, RackCatalogItemRequest $request)
    {
        $daten = $this->stammdaten($request);

        // Erst die alte Datei weg, sonst bleibt bei jedem Wechsel eine liegen,
        // die niemand mehr findet.
        if ($request->hasFile('image')) {
            $rackcatalogitem->bildLoeschen();
            $daten['image_path'] = $this->bildAblegen($request);
        } elseif ($request->boolean('image_remove')) {
            $rackcatalogitem->bildLoeschen();
            $daten['image_path'] = null;
        }

        $rackcatalogitem->update($daten);

        return redirect(route('admin.rackcatalogitem.index'));
    }

    /**
     * Loeschen ist gefahrlos: bereits verbaute Elemente haben die Bezeichnung
     * kopiert und bleiben unveraendert stehen. Nur das Foto verschwindet mit -
     * an seine Stelle tritt dort wieder die gezeichnete Blende.
     */
    public function destroy(RackCatalogItem $rackcatalogitem)
    {
        $rackcatalogitem->delete();

        return redirect(route('admin.rackcatalogitem.index'));
    }

    /**
     * Das hinterlegte Bild ausliefern.
     *
     * Hinter auth, aber nicht hinter isAdmin: Es steht in jeder Rack-Ansicht,
     * die ein Kunde sehen darf. Vertraulich ist es nicht - es zeigt eine
     * Frontblende -, die Datei liegt aber wie alle Dateien dieser App privat
     * und geht durch den Controller heraus.
     *
     * nosniff und eine feste Content-Type-Angabe: Der Browser soll die Datei
     * als Bild behandeln und nicht selbst raten, was sie sein koennte.
     */
    public function image(RackCatalogItem $rackcatalogitem)
    {
        $pfad = $rackcatalogitem->image_path;

        abort_if($pfad === null || ! Storage::disk('local')->exists($pfad), 404);

        return response(Storage::disk('local')->get($pfad), Response::HTTP_OK, [
            'Content-Type' => Storage::disk('local')->mimeType($pfad),
            'X-Content-Type-Options' => 'nosniff',
            // Ein Katalogbild aendert sich selten, steht aber in jedem Rack.
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** Die geprueften Werte ohne die Upload-Felder - die gehoeren nicht in die Spalten. */
    private function stammdaten(RackCatalogItemRequest $request): array
    {
        return Arr::except($request->validated(), ['image', 'image_remove']);
    }

    private function bildAblegen(RackCatalogItemRequest $request): string
    {
        return $request->file('image')->store(RackCatalogItem::BILDORDNER, 'local');
    }
}
