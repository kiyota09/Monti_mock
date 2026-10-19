<?php

use App\Http\Controllers\Logistics\ConductorController;
use App\Http\Controllers\Logistics\DispatchController;
use App\Http\Controllers\Logistics\DriverController;
use App\Http\Controllers\Logistics\DriversController;
use App\Http\Controllers\Logistics\FleetController;

use App\Http\Controllers\Logistics\LoadController;
use App\Http\Controllers\Logistics\LogisticsDashboardController;
use App\Http\Controllers\Logistics\ProofController;
use App\Http\Controllers\Logistics\ReportController;
use App\Http\Controllers\Logistics\RoutesController;
use App\Http\Controllers\Logistics\TrackingController;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LOGISTICS DASHBOARD & ROUTES
|--------------------------------------------------------------------------
| Every route carries page.permission so explicit per-page view/edit grants
| are enforced. Staff without explicit rows keep full access via the
| middleware's native shortcut.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'module.access:LOG'])->prefix('dashboard/logistics')->name('logistics.')->group(function () {
    // Main Dashboard
    Route::get('/', [LogisticsDashboardController::class, 'index'])
        ->middleware('page.permission:dashboard,view')->name('dashboard');

    // Fleet Management
    Route::get('/fleet', [FleetController::class, 'index'])
        ->middleware('page.permission:fleet,view')->name('fleet.index');
    Route::post('/fleet', [FleetController::class, 'store'])
        ->middleware('page.permission:fleet,edit')->name('fleet.store');
    Route::patch('/fleet/{truck}', [FleetController::class, 'update'])
        ->middleware('page.permission:fleet,edit')->name('fleet.update');
    Route::delete('/fleet/{truck}', [FleetController::class, 'destroy'])
        ->middleware('page.permission:fleet,edit')->name('fleet.destroy');

    // Drivers & Conductors
    Route::get('/drivers', [DriversController::class, 'index'])
        ->middleware('page.permission:drivers,view')->name('drivers.index');
    Route::post('/drivers', [DriversController::class, 'store'])
        ->middleware('page.permission:drivers,edit')->name('drivers.store');
    Route::get('/drivers/{driver}', [DriversController::class, 'show'])
        ->middleware('page.permission:drivers,view')->name('drivers.show');

    // Loading & Dispatch
    Route::get('/load', [LoadController::class, 'index'])
        ->middleware('page.permission:load,view')->name('load.index');
    Route::post('/load/dispatch', [LoadController::class, 'passToDispatch'])
        ->middleware('page.permission:load,edit')->name('load.pass');

    Route::get('/dispatch', [DispatchController::class, 'index'])
        ->middleware('page.permission:dispatch,view')->name('dispatch.index');
    Route::get('/dispatch/route-preview', [DispatchController::class, 'routePreview'])
        ->middleware('page.permission:dispatch,view')->name('dispatch.preview');
    Route::post('/dispatch', [DispatchController::class, 'store'])
        ->middleware('page.permission:dispatch,edit')->name('dispatch.store');
    Route::post('/dispatch/{delivery}/deliver', [DispatchController::class, 'deliver'])
        ->middleware('page.permission:dispatch,edit')->name('dispatch.deliver');

    // Portals (Driver & Conductor) — role work tools; writes gated as drivers/edit
    Route::get('/driver-portal', [DriverController::class, 'index'])
        ->middleware('page.permission:drivers,view')->name('driver.portal');
    Route::post('/driver-portal/{delivery}/transit', [DriverController::class, 'markInTransit'])
        ->middleware('page.permission:drivers,edit')->name('driver.transit');
    Route::post('/driver-portal/{delivery}/proof', [DriverController::class, 'uploadProof'])
        ->middleware('page.permission:drivers,edit')->name('driver.proof');

    // Driver portal tools
    Route::post('/driver-portal/duty', [DriverController::class, 'updateDutyStatus'])
        ->middleware('page.permission:drivers,edit')->name('driver.duty');
    Route::post('/driver-portal/duty/end', [DriverController::class, 'endDutyLog'])
        ->middleware('page.permission:drivers,edit')->name('driver.duty.end');
    Route::post('/driver-portal/inspections', [DriverController::class, 'storeInspection'])
        ->middleware('page.permission:drivers,edit')->name('driver.inspection.store');
    Route::post('/driver-portal/incidents', [DriverController::class, 'storeIncident'])
        ->middleware('page.permission:drivers,edit')->name('driver.incident.store');
    Route::post('/driver-portal/expenses', [DriverController::class, 'storeExpense'])
        ->middleware('page.permission:drivers,edit')->name('driver.expense.store');
    Route::post('/driver-portal/documents', [DriverController::class, 'storeDocument'])
        ->middleware('page.permission:drivers,edit')->name('driver.document.store');
    Route::delete('/driver-portal/documents/{document}', [DriverController::class, 'destroyDocument'])
        ->middleware('page.permission:drivers,edit')->name('driver.document.destroy');
    Route::post('/driver-portal/{delivery}/stops/{stop}/arrive', [DriverController::class, 'arriveStop'])
        ->middleware('page.permission:drivers,edit')->name('driver.stop.arrive');
    Route::post('/driver-portal/{delivery}/stops/{stop}/outcome', [DriverController::class, 'completeStop'])
        ->middleware('page.permission:drivers,edit')->name('driver.stop.update');

    Route::get('/conductor-portal', [ConductorController::class, 'index'])
        ->middleware('page.permission:drivers,view')->name('conductor.portal');
    Route::post('/conductor-portal/load-checks', [ConductorController::class, 'storeLoadCheck'])
        ->middleware('page.permission:drivers,edit')->name('conductor.load-check.store');
    Route::post('/conductor-portal/collections', [ConductorController::class, 'storeCollection'])
        ->middleware('page.permission:drivers,edit')->name('conductor.collection.store');
    Route::post('/conductor-portal/collections/{collection}/remit', [ConductorController::class, 'remitCollection'])
        ->middleware('page.permission:drivers,edit')->name('conductor.collection.remit');
    Route::post('/conductor-portal/returns', [ConductorController::class, 'storeReturn'])
        ->middleware('page.permission:drivers,edit')->name('conductor.return.store');
    Route::post('/conductor-portal/returns/{return}/resolve', [ConductorController::class, 'resolveReturn'])
        ->middleware('page.permission:drivers,edit')->name('conductor.return.resolve');
    Route::post('/conductor-portal/{delivery}/report', [ConductorController::class, 'storeReport'])
        ->middleware('page.permission:drivers,edit')->name('conductor.report');

    // Reports
    Route::get('/proof-of-delivery', [ProofController::class, 'index'])
        ->middleware('page.permission:proof,view')->name('proof.index');
    Route::get('/conductor-reports', [ReportController::class, 'index'])
        ->middleware('page.permission:reports,view')->name('reports.index');

    // ── Delivery Routes (map-based, client-linked) ────────────────────────
    Route::get('/routes', [RoutesController::class, 'index'])
        ->middleware('page.permission:routes,view')->name('routes');
    Route::post('/routes', [RoutesController::class, 'store'])
        ->middleware('page.permission:routes,edit')->name('routes.store');
    Route::put('/routes/{route}', [RoutesController::class, 'update'])
        ->middleware('page.permission:routes,edit')->name('routes.update');
    Route::delete('/routes/{route}', [RoutesController::class, 'destroy'])
        ->middleware('page.permission:routes,edit')->name('routes.destroy');



    // Tracking & Live Map
    Route::get('/tracking', [TrackingController::class, 'index'])
        ->middleware('page.permission:tracking,view')->name('tracking');
    Route::patch('/tracking/delivery-simulation', [TrackingController::class, 'updateDeliverySimulation'])
        ->middleware('page.permission:tracking,edit')->name('tracking.delivery-simulation');

    // Driver profile update
    Route::post('/driver/profile', [DriverController::class, 'updateProfile'])
        ->middleware('page.permission:drivers,edit')->name('driver.profile.update');

    // Test delivery completion
    Route::post('/driver/test-delivered/{delivery}', [DriverController::class, 'testDelivered'])
        ->middleware('page.permission:drivers,edit')->name('driver.test-delivered');
});
