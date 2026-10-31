<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImages;
use App\Http\Requests\DeviceModelRequest;
use App\Models\DeviceModel;
use App\Models\Setting;

/**
 * Pflege der Geraetemodelle im Adminbereich. Die Route-Gruppe laeuft bereits
 * behind the isAdmin middleware, hence no additional authorisation.
 *
 * The exception is image(): the picture stands in every rack view and must
 * therefore be reachable for customers too - the route lies outside the group.
 */
class DeviceModelController extends Controller
{
    use ManagesImages;

    public function index()
    {
        return view('admin.devicemodel.index', [
            'deviceModels' => DeviceModel::ordered()->paginate(Setting::seiteAdmin()),
            'deviceModelsCount' => DeviceModel::count(),
        ]);
    }

    public function create()
    {
        return view('admin.devicemodel.create');
    }

    public function store(DeviceModelRequest $request)
    {
        // The storage location does not hang on the record, so the image can
        // mit angelegt werden - sonst folgte auf jedes Anlegen ein Update.
        DeviceModel::create($this->handleImage($request, new DeviceModel, $this->stammdaten($request)));

        return redirect(route('admin.devicemodel.index'));
    }

    public function edit(DeviceModel $devicemodel)
    {
        return view('admin.devicemodel.edit', ['deviceModel' => $devicemodel]);
    }

    public function update(DeviceModel $devicemodel, DeviceModelRequest $request)
    {
        $devicemodel->update($this->handleImage($request, $devicemodel, $this->stammdaten($request)));

        return redirect(route('admin.devicemodel.index'));
    }

    /**
     * Loeschen betrifft kein Geraet: Die Dokumentation fuehrt Hersteller und
     * Modell selbst. Nur das Foto verschwindet, und an seine Stelle tritt
     * wieder die gezeichnete Blende des Geraetetyps.
     */
    public function destroy(DeviceModel $devicemodel)
    {
        $devicemodel->delete();

        return redirect(route('admin.devicemodel.index'));
    }

    /**
     * Serve the stored image.
     *
     * Hinter auth, aber ohne Mandantenpruefung - anders als bei Geraetedaten
     * und mit Absicht: Ein Modellfoto gehoert keinem Kunden. Es zeigt die
     * Frontblende eines Geraets, das man auch im Katalog des Herstellers
     * sieht, und genau deshalb soll es kundenuebergreifend gelten.
     */
    public function image(DeviceModel $devicemodel)
    {
        return $this->serveImage($devicemodel);
    }
}
