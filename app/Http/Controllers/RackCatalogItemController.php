<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImages;
use App\Http\Requests\RackCatalogItemRequest;
use App\Models\RackCatalogItem;
use App\Models\Setting;

/**
 * Maintenance of the rack catalog in the admin area. The route group
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
     * Deleting is harmless: items already mounted copied the label and stay
     * unchanged. Only the photo goes with it - there the drawn panel takes
     * its place again.
     */
    public function destroy(RackCatalogItem $rackcatalogitem)
    {
        $rackcatalogitem->delete();

        return redirect(route('admin.rackcatalogitem.index'));
    }

    /**
     * Serve the stored image.
     *
     * Behind auth but not behind isAdmin: it stands in every rack view a
     * customer may see. It is not confidential - it shows a front panel - but
     * the file lies privately like every file of this app and goes out
     * through the controller.
     *
     * nosniff and a fixed content type: the browser should treat the file as
     * an image and not guess for itself what it might be.
     */
    public function image(RackCatalogItem $rackcatalogitem)
    {
        return $this->serveImage($rackcatalogitem);
    }
}
