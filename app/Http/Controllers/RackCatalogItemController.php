<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PflegtBilder;
use App\Http\Requests\RackCatalogItemRequest;
use App\Models\RackCatalogItem;
use App\Models\Setting;

/**
 * Pflege des Rack-Katalogs im Adminbereich. Die Route-Gruppe laeuft bereits
 * hinter der isAdmin-Middleware, deshalb keine zusaetzliche Autorisierung.
 *
 * Ausnahme ist image(): Das Bild steht in jeder Rack-Ansicht und muss deshalb
 * auch fuer Kunden erreichbar sein - die Route liegt ausserhalb der Gruppe.
 */
class RackCatalogItemController extends Controller
{
    use PflegtBilder;

    public function index()
    {
        $rackCatalogItems = RackCatalogItem::ordered()->paginate(Setting::seiteAdmin());
        $rackCatalogItemsCount = RackCatalogItem::count();

        return view('admin.rackcatalogitem.index', compact('rackCatalogItems', 'rackCatalogItemsCount'));
    }

    public function create()
    {
        return view('admin.rackcatalogitem.create');
    }

    public function store(RackCatalogItemRequest $request)
    {
        // Der Ablageort haengt nicht am Datensatz, das Bild kann also gleich
        // mit angelegt werden - sonst folgte auf jedes Anlegen ein Update.
        RackCatalogItem::create($this->bildPflegen($request, new RackCatalogItem, $this->stammdaten($request)));

        return redirect(route('admin.rackcatalogitem.index'));
    }

    public function edit(RackCatalogItem $rackcatalogitem)
    {
        return view('admin.rackcatalogitem.edit', ['rackCatalogItem' => $rackcatalogitem]);
    }

    public function update(RackCatalogItem $rackcatalogitem, RackCatalogItemRequest $request)
    {
        $rackcatalogitem->update($this->bildPflegen($request, $rackcatalogitem, $this->stammdaten($request)));

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
        return $this->bildAusliefern($rackcatalogitem);
    }
}
