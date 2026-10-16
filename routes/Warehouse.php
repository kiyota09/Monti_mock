<?php

use App\Http\Controllers\Warehouse\MonitorController;
use App\Http\Controllers\Warehouse\PackageController;
use App\Http\Controllers\Warehouse\ReceivingController;
use App\Http\Controllers\Warehouse\RejectController;
use App\Http\Controllers\Warehouse\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Warehouse Module
|--------------------------------------------------------------------------
| Every route carries page.permission so explicit per-page view/edit grants
| are enforced. Staff without explicit rows keep full access via the
| middleware's native shortcut.
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/warehouse')->name('warehouse.')->middleware(['auth', 'verified', 'module.access:WAR'])->group(function () {
    // General Warehouse Management
    Route::get('/', [WarehouseController::class, 'index'])
        ->middleware('page.permission:warehouse,view')->name('index');
    Route::post('/', [WarehouseController::class, 'store'])
        ->middleware('page.permission:warehouse,edit')->name('store');
    Route::put('/{warehouse}', [WarehouseController::class, 'update'])
        ->middleware('page.permission:warehouse,edit')->name('update');
    Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])
        ->middleware('page.permission:warehouse,edit')->name('destroy');

    // Receiving & Logistics
    Route::get('/receiving', [ReceivingController::class, 'index'])
        ->middleware('page.permission:receiving,view')->name('receiving');
    Route::post('/receiving', [ReceivingController::class, 'receive'])
        ->middleware('page.permission:receiving,edit')->name('receiving.store');

    // Warehouse Monitor (The Grid) — now multi-floor
    Route::get('/monitor/{warehouse}', [MonitorController::class, 'show'])
        ->middleware('page.permission:monitor,view')->name('monitor');
    Route::post('/monitor/layout/{warehouse}', [MonitorController::class, 'updateLayout'])
        ->middleware('page.permission:monitor,edit')->name('monitor.layout');
    Route::post('/monitor/floors/{warehouse}', [MonitorController::class, 'storeFloor'])
        ->middleware('page.permission:monitor,edit')->name('monitor.floors.store');
    Route::put('/monitor/floors/{floor}', [MonitorController::class, 'updateFloor'])
        ->middleware('page.permission:monitor,edit')->name('monitor.floors.update');
    Route::delete('/monitor/floors/{floor}', [MonitorController::class, 'destroyFloor'])
        ->middleware('page.permission:monitor,edit')->name('monitor.floors.destroy');

    // DRAG & DROP ROUTE (Fixed naming)
    Route::post('/monitor/assign', [MonitorController::class, 'assignToShelf'])
        ->middleware('page.permission:monitor,edit')->name('monitor.assign');

    // MOVE stock across shelf / sector / floor / warehouse
    Route::post('/monitor/move/{stockItem}', [MonitorController::class, 'moveStock'])
        ->middleware('page.permission:monitor,edit')->name('monitor.move');

    Route::post('/monitor/use/{stockItem}', [MonitorController::class, 'useMaterial'])
        ->middleware('page.permission:monitor,edit')->name('monitor.use');

    // Packaging & Logistics
    Route::get('/packages', [PackageController::class, 'index'])
        ->middleware('page.permission:packages,view')->name('packages');
    Route::post('/packages/{package}/push', [PackageController::class, 'pushToLogistics'])
        ->middleware('page.permission:packages,edit')->name('packages.push');

    // Rejects
    Route::get('/rejects', [RejectController::class, 'index'])
        ->middleware('page.permission:reject,view')->name('rejects');
});
