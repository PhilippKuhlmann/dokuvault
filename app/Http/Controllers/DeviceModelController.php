<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeviceModelRequest;
use App\Models\DeviceModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pflege der Geraetemodelle im Adminbereich. Die Route-Gruppe laeuft bereits
 * hinter der isAdmin-Middleware, deshalb keine zusaetzliche Autorisierung.
 *
 * Ausnahme ist image(): Das Bild steht in jeder Rack-Ansicht und muss deshalb
 * auch fuer Kunden erreichbar sein - die Route liegt ausserhalb der Gruppe.
 */
class DeviceModelController extends Controller
{
    public function index()
    {
        return view('admin.devicemodel.index', [
            'deviceModels' => DeviceModel::ordered()->paginate(20),
            'deviceModelsCount' => DeviceModel::count(),
        ]);
    }

    public function create()
    {
        return view('admin.devicemodel.create');
    }

    public function store(DeviceModelRequest $request)
    {
        $modell = DeviceModel::create($this->stammdaten($request));

        if ($request->hasFile('image')) {
            $modell->update(['image_path' => $this->bildAblegen($request)]);
        }

        return redirect(route('admin.devicemodel.index'));
    }

    public function edit(DeviceModel $devicemodel)
    {
        return view('admin.devicemodel.edit', ['deviceModel' => $devicemodel]);
    }

    public function update(DeviceModel $devicemodel, DeviceModelRequest $request)
    {
        $daten = $this->stammdaten($request);

        // Erst die alte Datei weg, sonst bleibt bei jedem Wechsel eine liegen.
        if ($request->hasFile('image')) {
            $devicemodel->bildLoeschen();
            $daten['image_path'] = $this->bildAblegen($request);
        } elseif ($request->boolean('image_remove')) {
            $devicemodel->bildLoeschen();
            $daten['image_path'] = null;
        }

        $devicemodel->update($daten);

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
        $pfad = $devicemodel->image_path;

        abort_if($pfad === null || ! Storage::disk('local')->exists($pfad), 404);

        return response(Storage::disk('local')->get($pfad), Response::HTTP_OK, [
            'Content-Type' => Storage::disk('local')->mimeType($pfad),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** Die geprueften Werte ohne die Upload-Felder - die gehoeren nicht in die Spalten. */
    private function stammdaten(DeviceModelRequest $request): array
    {
        return Arr::except($request->validated(), ['image', 'image_remove']);
    }

    private function bildAblegen(DeviceModelRequest $request): string
    {
        return $request->file('image')->store(DeviceModel::BILDORDNER, 'local');
    }
}
