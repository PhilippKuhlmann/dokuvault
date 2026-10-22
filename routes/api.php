<?php

use App\Http\Controllers\API\AccesspointController;
use App\Http\Controllers\API\AgentController;
use App\Http\Controllers\API\ChecklistReportController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\Mobile\AnmeldungController;
use App\Http\Controllers\API\Mobile\DatenController;
use App\Http\Controllers\API\Mobile\DeviceController;
use App\Http\Controllers\API\Mobile\NetworkController as MobileNetworkController;
use App\Http\Controllers\API\Mobile\ObjectController;
use App\Http\Controllers\API\Mobile\PatchPanelController as MobilePatchPanelController;
use App\Http\Controllers\API\Mobile\RackController as MobileRackController;
use App\Http\Controllers\API\ServerController;
use App\Http\Controllers\API\SiteController;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// "aktiv" hinter "auth:sanctum", nicht davor: Erst muss der Guard den Nutzer
// aufgeloest haben, sonst sieht die Middleware niemanden und laesst alles
// durch. Damit arbeitet das Script eines gesperrten Benutzers nicht weiter -
// die Agent-Token oben haengen an keinem Benutzer und bleiben unberuehrt.
Route::middleware(['auth:sanctum', 'aktiv'])->get('/user', function (Request $request) {
    return $request->user();
});

// Self-Service-Dokumentation: Geräte melden sich per Agent-Token selbst.
// Own limit per token instead of the API limit per IP - see
// RouteServiceProvider: a whole site reports from one NAT address.
Route::middleware(['throttle:agent', 'agent'])->withoutMiddleware('throttle:api')->prefix('agent')->group(function () {
    Route::post('/proxmox', [AgentController::class, 'proxmox']);
    Route::post('/hyperv', [AgentController::class, 'hyperv']);
    Route::post('/vmware', [AgentController::class, 'vmware']);
    Route::post('/windows-server', [AgentController::class, 'windowsServer']);
    Route::post('/linux-server', [AgentController::class, 'linuxServer']);
    Route::post('/windows-ad', [AgentController::class, 'windowsAd']);
    Route::post('/windows-client', [AgentController::class, 'windowsClient']);
    Route::post('/unifi', [AgentController::class, 'unifi']);
    Route::post('/firewall', [AgentController::class, 'firewall']);
    Route::post('/microsoft365', [AgentController::class, 'microsoft365']);
    Route::post('/backup', [AgentController::class, 'backup']);
    // Installed agents report in and get the roles chosen on the agent page.
    Route::post('/checkin', [AgentController::class, 'checkin']);
    Route::post('/report', [AgentController::class, 'report']);
    // The Windows service fetches the current script before every run.
    Route::get('/script/{agent}', [AgentController::class, 'script'])->where('agent', '[a-z0-9-]+');
    // Installed agents update themselves (the agent program, not only the script).
    Route::get('/update/{dienst}', [AgentController::class, 'update'])->where('dienst', 'windows|proxmox|linux');
});

// The AD script of a checklist reports back. Its own key (one checklist,
// limited time) instead of a user token - see ChecklistReportController.
Route::post('/checklist-report', ChecklistReportController::class)->middleware('throttle:30,1');

// Die iOS-App. Vor der Kundenroute unten: "/{customer}" passt auf jedes
// einzelne Segment und fing sonst "/api/mobile" ab - siehe die Server-Routen.
// Lesen und Bearbeiten wie im Browser, mit denselben Rechten; Kennwoerter
// einzeln ueber POST, damit kein Zwischenspeicher sie als Antwort auf ein GET
// aufhebt.
Route::prefix('mobile')->group(function () {
    Route::middleware(SetLocale::class)->group(function () {
        Route::post('/login', [AnmeldungController::class, 'anmelden']);
        Route::post('/login/two-factor', [AnmeldungController::class, 'zweiteStufe']);
    });

    Route::middleware(['auth:sanctum', 'aktiv', SetLocale::class])->group(function () {
        Route::post('/logout', [AnmeldungController::class, 'abmelden']);
        Route::get('/me', [AnmeldungController::class, 'ich']);

        Route::get('/customers', [DatenController::class, 'kunden']);
        Route::get('/customers/{customer}', [DatenController::class, 'kunde'])->middleware('isCustomer');

        // Racks: the list is the "rack" type below, these show and edit one.
        // Scoped bindings: a rack of another customer (and an item of another
        // rack) is a 404, not just hidden.
        Route::prefix('/customers/{customer}/racks')->middleware('isCustomer')->scopeBindings()->group(function () {
            Route::post('/', [MobileRackController::class, 'store']);
            Route::get('/{rack}', [MobileRackController::class, 'show']);
            Route::put('/{rack}', [MobileRackController::class, 'update']);
            Route::delete('/{rack}', [MobileRackController::class, 'destroy']);
            Route::get('/{rack}/palette', [MobileRackController::class, 'palette']);
            Route::post('/{rack}/items', [MobileRackController::class, 'storeItem']);
            Route::patch('/{rack}/items/{item}', [MobileRackController::class, 'updateItem']);
            Route::delete('/{rack}/items/{item}', [MobileRackController::class, 'destroyItem']);
        });

        // IPAM: VLANs with their IP plan and reserved ranges. Before the
        // list route below - "/{typ}" would catch "networks".
        Route::prefix('/customers/{customer}/networks')->middleware('isCustomer')->scopeBindings()->group(function () {
            Route::get('/', [MobileNetworkController::class, 'index']);
            Route::post('/', [MobileNetworkController::class, 'store']);
            Route::get('/{network}', [MobileNetworkController::class, 'show']);
            Route::match(['put', 'patch'], '/{network}', [MobileNetworkController::class, 'update']);
            Route::delete('/{network}', [MobileNetworkController::class, 'destroy']);
            Route::post('/{network}/ip-ranges', [MobileNetworkController::class, 'storeRange']);
            Route::delete('/{network}/ip-ranges/{ipRange}', [MobileNetworkController::class, 'destroyRange']);
        });

        Route::get('/customers/{customer}/{typ}', [DatenController::class, 'liste'])->middleware('isCustomer');

        // Every type of config/forms.php: the form to build, then create,
        // edit, delete. POST on an object as well as PUT - a file upload
        // (multipart) only arrives with POST.
        Route::middleware('isCustomer')->group(function () {
            Route::get('/customers/{customer}/forms/{typ}', [ObjectController::class, 'createForm']);
            Route::post('/customers/{customer}/forms/{typ}/generate', [ObjectController::class, 'generate']);
            // Only form types: "/{typ}" would otherwise catch "patchpanels"
            // and every other fixed path below the customer.
            Route::post('/customers/{customer}/{typ}', [ObjectController::class, 'store'])->whereIn('typ', array_keys(config('forms')));
        });
        // Patch panels: like the racks, no form of their own - the list is
        // the "patchpanel" type, these create, edit and label the ports.
        Route::prefix('/customers/{customer}/patchpanels')->middleware('isCustomer')->scopeBindings()->group(function () {
            Route::post('/', [MobilePatchPanelController::class, 'store']);
            Route::get('/{patchpanel}', [MobilePatchPanelController::class, 'show']);
            Route::put('/{patchpanel}', [MobilePatchPanelController::class, 'update']);
            Route::delete('/{patchpanel}', [MobilePatchPanelController::class, 'destroy']);
            Route::put('/{patchpanel}/ports', [MobilePatchPanelController::class, 'updatePorts']);
        });

        // What hangs on a device: credentials, IP addresses, scan targets.
        Route::prefix('/objects/{typ}/{id}')->whereNumber('id')->group(function () {
            Route::get('/credentials', [DeviceController::class, 'credentials']);
            Route::post('/credentials', [DeviceController::class, 'attachCredential']);
            Route::delete('/credentials/{link}', [DeviceController::class, 'detachCredential'])->whereNumber('link');
            Route::get('/ip-addresses', [DeviceController::class, 'ipAddresses']);
            Route::post('/ip-addresses', [DeviceController::class, 'addIpAddress']);
            Route::delete('/ip-addresses/{ip}', [DeviceController::class, 'removeIpAddress'])->whereNumber('ip');
        });
        Route::prefix('/objects/scanner/{id}/scan-targets')->whereNumber('id')->group(function () {
            Route::get('/', [DeviceController::class, 'scanTargets']);
            Route::post('/', [DeviceController::class, 'addScanTarget']);
            Route::delete('/{ziel}', [DeviceController::class, 'removeScanTarget'])->whereNumber('ziel');
        });

        Route::get('/objects/{typ}/{id}/form', [ObjectController::class, 'editForm'])->whereNumber('id');
        Route::match(['put', 'patch', 'post'], '/objects/{typ}/{id}', [ObjectController::class, 'update'])->whereNumber('id');
        Route::delete('/objects/{typ}/{id}', [ObjectController::class, 'destroy'])->whereNumber('id');

        Route::get('/search', [DatenController::class, 'suche']);

        Route::get('/objects/{typ}/{id}', [DatenController::class, 'objekt'])->whereNumber('id');
        Route::post('/objects/{typ}/{id}/secret/{feld}', [DatenController::class, 'geheimnis'])->whereNumber('id');
        Route::post('/credentials/{link}/password', [DatenController::class, 'kennwort']);
    });
});

Route::middleware(['auth:sanctum', 'aktiv'])->group(function () {

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);

    // Vor der Kundenroute: "/{customer}" passt auf jedes einzelne Segment und
    // fing "GET /api/servers" ab - die Anfrage suchte einen Kunden namens
    // "servers" und endete in einem 404. Die Server-Schnittstelle war damit
    // nicht erreichbar, ohne dass es jemandem auffiel.
    Route::get('servers', [ServerController::class, 'index']);
    Route::get('servers/{server}', [ServerController::class, 'show']);
    Route::post('servers', [ServerController::class, 'store']);
    Route::put('servers/{server}', [ServerController::class, 'update']);
    Route::delete('servers/{server}', [ServerController::class, 'delete']);
    // isCustomer: verhindert, dass ein auf einen Kunden beschraenkter Token
    // (customer_id gesetzt) Daten eines anderen Kunden ueber die Route
    // abruft. Admin-/Techniker-Token (kein customer_id) bleiben unbeschraenkt.
    Route::get('/{customer}', [CustomerController::class, 'show'])->middleware('isCustomer');

    Route::prefix('{customer}')->middleware('isCustomer')->group(function () {

        Route::get('/sites', [SiteController::class, 'index']);
        Route::post('/sites', [SiteController::class, 'store']);
        Route::get('/sites/{site}', [SiteController::class, 'show']);

        Route::get('/accesspoints', [AccesspointController::class, 'index']);
        Route::get('/accesspoints/{accesspoint}', [AccesspointController::class, 'show']);
        Route::post('/accesspoints', [AccesspointController::class, 'store']);
        Route::put('/accesspoints/{accesspoint}', [AccesspointController::class, 'update']);
        Route::delete('/accesspoints/{accesspoint}', [AccesspointController::class, 'delete']);

    });

});
