<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PflegtBilder;
use App\Http\Requests\DeviceModelRequest;
use App\Models\DeviceModel;
use App\Models\Setting;

/**
 * Pflege der Geraetemodelle im Adminbereich. Die Route-Gruppe laeuft bereits
 * hinter der isAdmin-Middleware, deshalb keine zusaetzliche Autorisierung.
 *
 * Ausnahme ist image(): Das Bild steht in jeder Rack-Ansicht und muss deshalb
 * auch fuer Kunden erreichbar sein - die Route liegt ausserhalb der Gruppe.
 */
class DeviceModelController extends Controller
{
    use PflegtBilder;

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
        // Der Ablageort haengt nicht am Datensatz, das Bild kann also gleich
        // mit angelegt werden - sonst folgte auf jedes Anlegen ein Update.
        DeviceModel::create($this->bildPflegen($request, new DeviceModel, $this->stammdaten($request)));

        return redirect(route('admin.devicemodel.index'));
    }

    public function edit(DeviceModel $devicemodel)
    {
        return view('admin.devicemodel.edit', ['deviceModel' => $devicemodel]);
    }

    public function update(DeviceModel $devicemodel, DeviceModelRequest $request)
    {
        $devicemodel->update($this->bildPflegen($request, $devicemodel, $this->stammdaten($request)));

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
     * Das hinterlegte Bild ausliefern.
     *
     * Hinter auth, aber ohne Mandantenpruefung - anders als bei Geraetedaten
     * und mit Absicht: Ein Modellfoto gehoert keinem Kunden. Es zeigt die
     * Frontblende eines Geraets, das man auch im Katalog des Herstellers
     * sieht, und genau deshalb soll es kundenuebergreifend gelten.
     */
    public function image(DeviceModel $devicemodel)
    {
        return $this->bildAusliefern($devicemodel);
    }
}
