<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImages;
use App\Http\Requests\RackCatalogItemRequest;
use App\Models\RackCatalogItem;
use App\Models\Setting;

/**
 * Pflege des Rack-Katalogs im Adminbereich. Die Route-Gruppe laeuft bereits
 * behind the isAdmin middleware, hence no additional authorisation.
 *
 * The exception is image(): the picture stands in every rack view and must
 * therefore be reachable for customers too - the route lies outside the group.
 */
class RackCatalogItemController extends Controller
{
    use ManagesImages;

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
        // The storage location does not hang on the record, so the image can
        // mit angelegt werden - sonst folgte auf jedes Anlegen ein Update.
        RackCatalogItem::create($this->handleImage($request, new RackCatalogItem, $this->stammdaten($request)));

        return redirect(route('admin.rackcatalogitem.index'));
    }

    public function edit(RackCatalogItem $rackcatalogitem)
    {
        return view('admin.rackcatalogitem.edit', ['rackCatalogItem' => $rackcatalogitem]);
    }

    public function update(RackCatalogItem $rackcatalogitem, RackCatalogItemRequest $request)
    {
        $rackcatalogitem->update($this->handleImage($request, $rackcatalogitem, $this->stammdaten($request)));

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
     * Serve the stored image.
     *
     * Hinter auth, aber nicht hinter isAdmin: Es steht in jeder Rack-Ansicht,
     * die ein Kunde sehen darf. Vertraulich ist es nicht - es zeigt eine
     * Frontblende -, die Datei liegt aber wie alle Dateien dieser App privat
     * und geht durch den Controller heraus.
     *
     * nosniff and a fixed content type: the browser should treat the file as
     * an image and not guess for itself what it might be.
     */
    public function image(RackCatalogItem $rackcatalogitem)
    {
        return $this->serveImage($rackcatalogitem);
    }
}
