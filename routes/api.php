<?php

use App\Http\Controllers\API\AccesspointController;
use App\Http\Controllers\API\AgentController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\ServerController;
use App\Http\Controllers\API\SiteController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Self-Service-Dokumentation: Geräte melden sich per Agent-Token selbst.
Route::middleware('agent')->prefix('agent')->group(function () {
    Route::post('/proxmox', [AgentController::class, 'proxmox']);
    Route::post('/windows-ad', [AgentController::class, 'windowsAd']);
    Route::post('/windows-client', [AgentController::class, 'windowsClient']);
});

Route::middleware(['auth:sanctum'])->group(function () {

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
