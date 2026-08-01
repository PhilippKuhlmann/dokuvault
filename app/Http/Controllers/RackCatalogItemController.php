<?php

namespace App\Http\Controllers;

use App\Http\Requests\RackCatalogItemRequest;
use App\Models\RackCatalogItem;

/**
 * Pflege des Rack-Katalogs im Adminbereich. Die Route-Gruppe laeuft bereits
 * hinter der isAdmin-Middleware, deshalb keine zusaetzliche Autorisierung.
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
        RackCatalogItem::create($request->validated());

        return redirect(route('admin.rackcatalogitem.index'));
    }

    public function edit(RackCatalogItem $rackcatalogitem)
    {
        return view('admin.rackcatalogitem.edit', ['rackCatalogItem' => $rackcatalogitem]);
    }

    public function update(RackCatalogItem $rackcatalogitem, RackCatalogItemRequest $request)
    {
        $rackcatalogitem->update($request->validated());

        return redirect(route('admin.rackcatalogitem.index'));
    }

    /**
     * Loeschen ist gefahrlos: bereits verbaute Elemente haben die Bezeichnung
     * kopiert und bleiben unveraendert stehen.
     */
    public function destroy(RackCatalogItem $rackcatalogitem)
    {
        $rackcatalogitem->delete();

        return redirect(route('admin.rackcatalogitem.index'));
    }
}
