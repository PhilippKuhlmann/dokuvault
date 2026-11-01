<?php

use App\Http\Controllers\API\AccesspointController;
use App\Http\Controllers\API\AgentController;
use App\Http\Controllers\API\ChecklistReportController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\Mobile\AgentController as MobileAgentController;
use App\Http\Controllers\API\Mobile\ChecklistController as MobileChecklistController;
use App\Http\Controllers\API\Mobile\DashboardController as MobileDashboardController;
use App\Http\Controllers\API\Mobile\DatenController;
use App\Http\Controllers\API\Mobile\DeviceController;
use App\Http\Controllers\API\Mobile\LoginController;
use App\Http\Controllers\API\Mobile\NetworkController as MobileNetworkController;
use App\Http\Controllers\API\Mobile\NetworkMapController as MobileNetworkMapController;
use App\Http\Controllers\API\Mobile\ObjectController;
use App\Http\Controllers\API\Mobile\PatchPanelController as MobilePatchPanelController;
use App\Http\Controllers\API\Mobile\PdfController as MobilePdfController;
use App\Http\Controllers\API\Mobile\ProfilController as MobileProfilController;
use App\Http\Controllers\API\Mobile\RackController as MobileRackController;
use App\Http\Controllers\API\Mobile\TrashController as MobileTrashController;
use App\Http\Controllers\API\Mobile\WizardController as MobileWizardController;
use App\Http\Controllers\API\ServerController;
use App\Http\Controllers\API\SiteController;
use App\Http\Middleware\RequireIntegrationToken;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SetLocale;
use App\Models\Accesspoint;
use App\Models\Server;
use App\Models\Site;
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

// "active" after "auth:sanctum", not before: the guard has to resolve the
// user first, or the middleware sees nobody and lets everything through.
// That way the script of a locked user does not keep working - the agent
// tokens above hang on no user and stay untouched.
Route::middleware(['auth:sanctum', 'active', RequireTwoFactor::class])->get('/user', function (Request $request) {
    return $request->user();
});

// Self-service documentation: devices report in with an agent token.
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
    Route::get('/update/{service}', [AgentController::class, 'update'])->where('service', 'windows|proxmox|linux');
});

// The AD script of a checklist reports back. Its own key (one checklist,
// limited time) instead of a user token - see ChecklistReportController.
Route::post('/checklist-report', ChecklistReportController::class)->middleware('throttle:30,1');

// The iOS app. Before the customer route below: "/{customer}" matches any
// single segment and would otherwise catch "/api/mobile" - see the server
// routes. Reading and editing as in the browser, with the same permissions;
// passwords one at a time through POST, so no cache keeps them as the answer
// to a GET.
Route::prefix('mobile')->group(function () {
    Route::middleware(SetLocale::class)->group(function () {
        Route::post('/login', [LoginController::class, 'login']);
        Route::post('/login/two-factor', [LoginController::class, 'zweiteStufe']);
    });

    Route::middleware(['auth:sanctum', 'active', SetLocale::class, RequireTwoFactor::class])->group(function () {
        Route::post('/logout', [LoginController::class, 'abmelden']);
        Route::get('/me', [LoginController::class, 'ich']);

        // The own profile: name, email, language, password.
        Route::get('/profile', [MobileProfilController::class, 'show']);
        Route::match(['put', 'patch'], '/profile', [MobileProfilController::class, 'aendern']);
        Route::put('/profile/password', [MobileProfilController::class, 'kennwort']);

        // Setting up the second factor: begin, confirm, new recovery codes,
        // switch off. Without a session an encrypted "vorgang" carries the
        // secret that was begun.
        Route::post('/profile/two-factor/begin', [MobileProfilController::class, 'beginTwoFactor']);
        Route::post('/profile/two-factor/confirm', [MobileProfilController::class, 'confirmTwoFactor']);
        Route::post('/profile/two-factor/recovery-codes', [MobileProfilController::class, 'recoveryCodes']);
        Route::delete('/profile/two-factor', [MobileProfilController::class, 'disableTwoFactor']);

        // Delete the own account - with a password and not undoable.
        Route::delete('/profile', [MobileProfilController::class, 'delete']);
        // Changelog and version - in the browser both hang under the
        // sidebar.
        Route::get('/changelog', [MobileProfilController::class, 'changelog']);

        Route::get('/customers', [DatenController::class, 'kunden']);
        Route::get('/customers/{customer}', [DatenController::class, 'kunde'])->middleware('isCustomer');
        // Before the type list below: "/{typ}" matches any single segment and
        // would otherwise catch "dashboard".
        Route::get('/customers/{customer}/dashboard', [MobileDashboardController::class, 'show'])->middleware('isCustomer');

        // Trash: before the type list below as well.
        Route::middleware('isCustomer')->group(function () {
            Route::get('/customers/{customer}/trash', [MobileTrashController::class, 'index']);
            // The documentation as a PDF: order, ask after, collect.
            // Without scopeBindings - the customer carries no relation to the
            // jobs; the controller checks it itself, as in the browser.
            Route::prefix('/customers/{customer}/pdf')->group(function () {
                Route::get('/', [MobilePdfController::class, 'stand']);
                Route::post('/', [MobilePdfController::class, 'bestellen']);
                Route::get('/{pdfExport}', [MobilePdfController::class, 'load'])->whereNumber('pdfExport');
            });

            // Network map: internet -> gateway -> VLAN -> devices.
            Route::get('/customers/{customer}/network-map', [MobileNetworkMapController::class, 'show']);

            // Initial-survey wizard: step by step through the basics.
            // Filling in happens with the forms below.
            Route::prefix('/customers/{customer}/wizard')->group(function () {
                Route::get('/', [MobileWizardController::class, 'index']);
                Route::post('/start', [MobileWizardController::class, 'start']);
                Route::post('/next', [MobileWizardController::class, 'weiter']);
                Route::post('/goto', [MobileWizardController::class, 'springen']);
                Route::post('/complete', [MobileWizardController::class, 'abschliessen']);
            });

            // Auto-documentation: which agents run and what they report.
            // scopeBindings: an agent of another customer is a 404, not just
            // hidden.
            Route::prefix('/customers/{customer}/agents')->scopeBindings()->group(function () {
                Route::get('/', [MobileAgentController::class, 'index']);
                // The parameter names match the relations on the customer
                // (agentInstallations, agentTokens) - that is what
                // scopeBindings hangs on.
                Route::put('/installations/{agentInstallation}', [MobileAgentController::class, 'update']);
                Route::post('/installations/{agentInstallation}/run', [MobileAgentController::class, 'run']);
                Route::post('/tokens/{agentToken}/lock', [MobileAgentController::class, 'lock']);
                Route::post('/tokens/{agentToken}/unlock', [MobileAgentController::class, 'unlock']);
            });

            // Checklists. scopeBindings: a list of another customer (and an
            // item of another list) is a 404.
            Route::prefix('/customers/{customer}/checklists')->scopeBindings()->group(function () {
                Route::get('/', [MobileChecklistController::class, 'index']);
                // Requesting: fetch the choices first, then create. Before
                // "/{checklist}", or that would catch the fixed paths.
                Route::get('/form', [MobileChecklistController::class, 'formular']);
                Route::post('/onboarding', [MobileChecklistController::class, 'onboarding']);
                Route::post('/offboarding', [MobileChecklistController::class, 'offboarding']);
                Route::get('/{checklist}', [MobileChecklistController::class, 'show'])->whereNumber('checklist');
                Route::post('/{checklist}/items/{item}/toggle', [MobileChecklistController::class, 'toggle']);
                Route::post('/{checklist}/items/{item}/approve', [MobileChecklistController::class, 'approve']);
                // The PowerShell script for the AD part - to hand to the
                // machine it runs on.
                Route::get('/{checklist}/ad-script', [MobileChecklistController::class, 'adSkript']);
            });
            Route::post('/customers/{customer}/trash/{typ}/{id}/restore', [MobileTrashController::class, 'restore'])
                ->whereNumber('id');
        });

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

// The legacy REST API for integrations. Every route checks the same right
// as the browser (->can(), before any validation), only integration tokens from
// the admin area get in, and records resolve within the customer of the URL.
Route::middleware(['auth:sanctum', 'active', RequireTwoFactor::class, RequireIntegrationToken::class])->group(function () {

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store'])->can('admin_customer');

    // Before the customer route: "/{customer}" matches any single segment and
    // caught "GET /api/servers" - the request looked for a customer named
    // "servers" and ended in a 404. The server interface was unreachable that
    // way, without anyone noticing.
    Route::get('servers', [ServerController::class, 'index'])->can('viewAny', Server::class);
    Route::get('servers/{server}', [ServerController::class, 'show'])->can('viewAny', Server::class);
    Route::post('servers', [ServerController::class, 'store'])->can('create', Server::class);
    Route::put('servers/{server}', [ServerController::class, 'update'])->can('update', Server::class);
    Route::delete('servers/{server}', [ServerController::class, 'delete'])->can('delete', Server::class);
    // isCustomer: keeps a token limited to one customer (customer_id set)
    // from fetching another customer's data through the route. Admin and
    // technician tokens (no customer_id) stay unrestricted.
    Route::get('/{customer}', [CustomerController::class, 'show'])->middleware('isCustomer');

    // scopeBindings: {site} and {accesspoint} resolve through the customer -
    // a foreign id behind the own customer's URL is a 404.
    Route::prefix('{customer}')->middleware('isCustomer')->scopeBindings()->group(function () {

        Route::get('/sites', [SiteController::class, 'index'])->can('viewAny', Site::class);
        Route::post('/sites', [SiteController::class, 'store'])->can('create', Site::class);
        Route::get('/sites/{site}', [SiteController::class, 'show'])->can('viewAny', Site::class);

        Route::get('/accesspoints', [AccesspointController::class, 'index'])->can('viewAny', Accesspoint::class);
        Route::get('/accesspoints/{accesspoint}', [AccesspointController::class, 'show'])->can('viewAny', Accesspoint::class);
        Route::post('/accesspoints', [AccesspointController::class, 'store'])->can('create', Accesspoint::class);
        Route::put('/accesspoints/{accesspoint}', [AccesspointController::class, 'update'])->can('update', Accesspoint::class);
        Route::delete('/accesspoints/{accesspoint}', [AccesspointController::class, 'delete'])->can('delete', Accesspoint::class);

    });

});
