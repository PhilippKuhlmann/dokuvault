<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImages;
use App\Http\Requests\DeviceModelRequest;
use App\Models\DeviceModel;
use App\Models\Setting;

/**
 * Maintenance of the device models in the admin area. The route group
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
     * Deleting affects no device: the documentation carries manufacturer and
     * model itself. Only the photo disappears, and the drawn panel of the
     * device kind takes its place again.
     */
    public function destroy(DeviceModel $devicemodel)
    {
        $devicemodel->delete();

        return redirect(route('admin.devicemodel.index'));
    }

    /**
     * Serve the stored image.
     *
     * Behind auth but without a tenant check - unlike with device data, and
     * on purpose: a model photo belongs to no customer. It shows the front
     * panel of a device one also sees in the manufacturer's catalog, and for
     * exactly that reason it should apply across customers.
     */
    public function image(DeviceModel $devicemodel)
    {
        return $this->serveImage($devicemodel);
    }
}
