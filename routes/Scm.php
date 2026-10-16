<?php

use App\Http\Controllers\Scm\ScmAnalyticsController;
use App\Http\Controllers\Scm\ScmDashboardController;
use App\Http\Controllers\Scm\ScmDeliveriesController;
use App\Http\Controllers\Scm\ScmPlanningController;
use App\Http\Controllers\Scm\ScmProcurementOrderController;
use App\Http\Controllers\Scm\ScmPurchaseController;
use App\Http\Controllers\Scm\ScmSalesOrderController;
use App\Http\Controllers\Scm\ScmVendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Supply Chain Management (SCM) Routes
|--------------------------------------------------------------------------
| Every route carries page.permission so explicit per-page view/edit grants
| are enforced. Staff without explicit rows keep full access via the
| middleware's native shortcut.
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/scm')->name('scm.')->middleware(['auth', 'verified', 'module.access:SCM'])->group(function () {
    // Command Center (SCOR: Enable — pipeline overview)
    Route::get('/dashboard', [ScmDashboardController::class, 'index'])
        ->middleware('page.permission:dashboard,view')->name('dashboard');

    // Sales Orders (from ECO — sustainability already checked in the ECO
    // Push Center; SCM re-verifies live at push-to-production, so the manual
    // check-inventory buttons are gone)
    Route::get('/sales-orders', [ScmSalesOrderController::class, 'index'])
        ->middleware('page.permission:sales,view')->name('sales-orders');
    Route::post('/sales-orders/{order}/push-to-production', [ScmSalesOrderController::class, 'pushToProduction'])
        ->middleware('page.permission:sales,edit')->name('sales-order.push-to-production');
    Route::post('/sales-orders/sales/{salesOrder}/push-to-production', [ScmSalesOrderController::class, 'pushToProductionSalesOrder'])
        ->middleware('page.permission:sales,edit')->name('sales-order.push-to-production-sales');

    // Procurement Orders (requests from Inventory)
    Route::get('/procurement-orders', [ScmProcurementOrderController::class, 'index'])
        ->middleware('page.permission:procurement,view')->name('procurement-orders');
    Route::post('/procurement-orders/{materialRequest}/send', [ScmProcurementOrderController::class, 'sendToProcurementModule'])
        ->middleware('page.permission:procurement,edit')->name('procurement-order.send');

    // Vendor Management
    Route::get('/vendors', [ScmVendorController::class, 'index'])
        ->middleware('page.permission:vendor,view')->name('vendors');
    Route::post('/vendors/{registration}/approve', [ScmVendorController::class, 'approve'])
        ->middleware('page.permission:vendor,edit')->name('vendors.approve');
    Route::post('/vendors/{registration}/reject', [ScmVendorController::class, 'reject'])
        ->middleware('page.permission:vendor,edit')->name('vendors.reject');

    // Demand & Materials Planning (SCOR: Plan — textile MRP-lite, read-only)
    Route::get('/planning', [ScmPlanningController::class, 'index'])
        ->middleware('page.permission:planning,view')->name('planning');

    // Purchase Order Tracking (SCOR: Source — pipeline visibility; PRO executes)
    Route::get('/purchase-orders', [ScmPurchaseController::class, 'index'])
        ->middleware('page.permission:purchase,view')->name('purchase-orders');

    // Inbound Deliveries & QC (SCOR: Source/Deliver — goods receipt history)
    Route::get('/deliveries', [ScmDeliveriesController::class, 'index'])
        ->middleware('page.permission:deliveries,view')->name('deliveries');

    // Analytics (SCOR: Enable — supplier scorecard, spend, funnel)
    Route::get('/analytics', [ScmAnalyticsController::class, 'index'])
        ->middleware('page.permission:analytics,view')->name('analytics');

    // Access control is centralized in the CEO module (view/request)
    // and IT Access Control (fulfilment).
});
