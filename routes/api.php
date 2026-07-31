<?php

use App\Http\Controllers\API\AccesspointController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\RoomController;
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
    Route::post('/proxmox', [\App\Http\Controllers\API\AgentController::class, 'proxmox']);
    Route::post('/windows-ad', [\App\Http\Controllers\API\AgentController::class, 'windowsAd']);
});


Route::middleware(['auth:sanctum'])->group(function () {

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/{customer}', [CustomerController::class, 'show']);
    Route::post('/customers', [CustomerController::class, 'store']);


    Route::prefix('{customer}')->group(function () {

        Route::get('/sites', [SiteController::class, 'index']);
        Route::post('/sites', [SiteController::class, 'store']);
        Route::get('/sites/{site}', [SiteController::class, 'show']);

        Route::get('/{site}/rooms', [RoomController::class, 'index']);
        Route::post('/{site}/rooms', [RoomController::class, 'store']);
        Route::get('/{site}/rooms/{room}', [RoomController::class, 'show']);

        Route::get('/accesspoints', [AccesspointController::class, 'index']);
        Route::get('/accesspoints/{accesspoint}', [AccesspointController::class, 'show']);
        Route::post('/accesspoints', [AccesspointController::class, 'store']);
        Route::put('/accesspoints/{accesspoint}', [AccesspointController::class, 'update']);
        Route::delete('/accesspoints/{accesspoint}', [AccesspointController::class, 'delete']);

    });

    // Server
    Route::get('servers', [ServerController::class, 'index']);
    Route::get('servers/{server}', [ServerController::class, 'show']);
    Route::post('servers', [ServerController::class, 'store']);
    Route::put('servers/{server}', [ServerController::class, 'update']);
    Route::delete('servers/{server}', [ServerController::class, 'delete']);







});
