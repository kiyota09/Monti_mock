<?php

use App\Http\Controllers\It\AssetController;
use App\Http\Controllers\It\ChangeController;
use App\Http\Controllers\It\GeolocationController;
use App\Http\Controllers\It\ItAccessController;
use App\Http\Controllers\It\ItAccessControlController;
use App\Http\Controllers\It\ItDashboardController;
use App\Http\Controllers\It\KnowledgeController;
use App\Http\Controllers\It\MonitoringController;
use App\Http\Controllers\It\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| IT & Systems Admin (IT) Routes — ITIL-aligned service management
|--------------------------------------------------------------------------
| Every functional route carries page.permission so explicit per-page
| view/edit grants (IT Access Control) are actually enforced. Native IT
| managers/staff without explicit rows keep full access via the
| middleware's legacy shortcut.
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/it')->name('it.')->middleware(['auth', 'verified', 'module.access:IT'])->group(function () {
    // Operations dashboard (role-aware: manager overview / staff queue)
    Route::get('/', [ItDashboardController::class, 'index'])
        ->middleware('page.permission:dashboard,view')->name('dashboard');

    // Service desk: incidents + service requests
    Route::get('/tickets', [TicketController::class, 'index'])
        ->middleware('page.permission:tickets,view')->name('tickets');
    Route::post('/tickets', [TicketController::class, 'store'])
        ->middleware('page.permission:tickets,edit')->name('tickets.store');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update'])
        ->middleware('page.permission:tickets,edit')->name('tickets.update');
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'comment'])
        ->middleware('page.permission:tickets,edit')->name('tickets.comment');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])
        ->middleware(['position:manager', 'page.permission:tickets,edit'])->name('tickets.destroy');

    // IT asset register
    Route::get('/assets', [AssetController::class, 'index'])
        ->middleware('page.permission:assets,view')->name('assets');
    Route::post('/assets', [AssetController::class, 'store'])
        ->middleware(['position:manager', 'page.permission:assets,edit'])->name('assets.store');
    Route::put('/assets/{asset}', [AssetController::class, 'update'])
        ->middleware(['position:manager', 'page.permission:assets,edit'])->name('assets.update');
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])
        ->middleware(['position:manager', 'page.permission:assets,edit'])->name('assets.destroy');
    Route::post('/assets/{asset}/assign', [AssetController::class, 'assign'])
        ->middleware(['position:manager', 'page.permission:assets,edit'])->name('assets.assign');
    Route::post('/assets/{asset}/return', [AssetController::class, 'returnAsset'])
        ->middleware('page.permission:assets,edit')->name('assets.return');

    // Systems & network monitoring
    Route::get('/monitoring', [MonitoringController::class, 'index'])
        ->middleware('page.permission:monitoring,view')->name('monitoring');
    Route::post('/monitoring', [MonitoringController::class, 'store'])
        ->middleware(['position:manager', 'page.permission:monitoring,edit'])->name('monitoring.store');
    Route::post('/monitoring/{system}/check', [MonitoringController::class, 'check'])
        ->middleware('page.permission:monitoring,edit')->name('monitoring.check');
    Route::delete('/monitoring/{system}', [MonitoringController::class, 'destroy'])
        ->middleware(['position:manager', 'page.permission:monitoring,edit'])->name('monitoring.destroy');

    // Knowledge base
    Route::get('/knowledge', [KnowledgeController::class, 'index'])
        ->middleware('page.permission:knowledge,view')->name('knowledge');
    Route::post('/knowledge', [KnowledgeController::class, 'store'])
        ->middleware('page.permission:knowledge,edit')->name('knowledge.store');
    Route::put('/knowledge/{article}', [KnowledgeController::class, 'update'])
        ->middleware('page.permission:knowledge,edit')->name('knowledge.update');
    Route::delete('/knowledge/{article}', [KnowledgeController::class, 'destroy'])
        ->middleware(['position:manager', 'page.permission:knowledge,edit'])->name('knowledge.destroy');
    Route::post('/knowledge/{article}/read', [KnowledgeController::class, 'markRead'])
        ->middleware('page.permission:knowledge,view')->name('knowledge.read');

    // Change enablement
    Route::get('/changes', [ChangeController::class, 'index'])
        ->middleware('page.permission:changes,view')->name('changes');
    Route::post('/changes', [ChangeController::class, 'store'])
        ->middleware('page.permission:changes,edit')->name('changes.store');
    Route::post('/changes/{change}/approve', [ChangeController::class, 'approve'])
        ->middleware(['position:manager', 'page.permission:changes,edit'])->name('changes.approve');
    Route::post('/changes/{change}/reject', [ChangeController::class, 'reject'])
        ->middleware(['position:manager', 'page.permission:changes,edit'])->name('changes.reject');
    Route::post('/changes/{change}/status', [ChangeController::class, 'setStatus'])
        ->middleware('page.permission:changes,edit')->name('changes.status');
    Route::delete('/changes/{change}', [ChangeController::class, 'destroy'])
        ->middleware(['position:manager', 'page.permission:changes,edit'])->name('changes.destroy');

    // Access control (page grants)
    Route::get('/access', [ItAccessController::class, 'index'])
        ->middleware(['position:manager', 'page.permission:access,view'])->name('access');
    Route::post('/access/update', [ItAccessController::class, 'update'])
        ->middleware(['position:manager', 'page.permission:access,edit'])->name('access.update');

    // Organization-wide access control (org chart: accounts, promotion, per-page view/edit)
    Route::get('/access-control', [ItAccessControlController::class, 'index'])
        ->middleware(['position:manager', 'page.permission:access_control,view'])->name('access-control');
    Route::post('/access-control/status', [ItAccessControlController::class, 'setStatus'])
        ->middleware(['position:manager', 'page.permission:access_control,edit'])->name('access-control.status');
    Route::post('/access-control/position', [ItAccessControlController::class, 'updatePosition'])
        ->middleware(['position:manager', 'page.permission:access_control,edit'])->name('access-control.position');
    Route::post('/access-control/modules', [ItAccessControlController::class, 'updateModules'])
        ->middleware(['position:manager', 'page.permission:access_control,edit'])->name('access-control.modules');
    Route::post('/access-control/pages', [ItAccessControlController::class, 'updatePages'])
        ->middleware(['position:manager', 'page.permission:access_control,edit'])->name('access-control.pages');
    Route::post('/access-control/requests/{id}/reject', [ItAccessControlController::class, 'rejectRequest'])
        ->middleware(['position:manager', 'page.permission:access_control,edit'])->name('access-control.requests.reject');

    // Strategic geolocation hub — multiple company sites (HQ, warehouses, branches)
    Route::get('/location', [GeolocationController::class, 'index'])
        ->middleware('page.permission:location,view')->name('location.index');
    Route::post('/location/sync', [GeolocationController::class, 'store'])
        ->middleware('page.permission:location,edit')->name('location.store');
    Route::put('/location/{location}', [GeolocationController::class, 'update'])
        ->middleware('page.permission:location,edit')->name('location.update');
    Route::post('/location/{location}/toggle', [GeolocationController::class, 'toggle'])
        ->middleware('page.permission:location,edit')->name('location.toggle');
    Route::delete('/location/{location}', [GeolocationController::class, 'destroy'])
        ->middleware('page.permission:location,edit')->name('location.destroy');

    // Audit trail of access-control changes
    Route::get('/access-logs', [ItAccessControlController::class, 'logs'])
        ->middleware(['position:manager', 'page.permission:access_logs,view'])->name('access-logs');
});
