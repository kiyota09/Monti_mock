<?php

use App\Http\Controllers\Ord\OrdDashboardController;
use App\Http\Controllers\Ord\OrdDeliveryController;
use App\Http\Controllers\Ord\OrdOrdersController;
use App\Http\Controllers\Ord\OrdProductionsController;
use App\Http\Controllers\Ord\OrdReturnsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Order Management (ORD) Routes — Monti Textile order-to-cash pipeline
|--------------------------------------------------------------------------
|
| Intake (ECO push / manual PO) -> Confirm -> Plan -> Produce (MAN) ->
| Pack/Warehouse -> Dispatch (Logistics) -> Deliver/POD -> Bill & Close,
| with returns (RMA) and a full status audit trail.
|
| Route names ord.orders / ord.productions / ord.delivery
| are kept stable for the sidebar. Access control lives in the CEO
| module + IT Access Control.
|
*/
Route::prefix('dashboard/ord')->name('ord.')->middleware(['auth', 'verified', 'module.access:ORD'])->group(function () {

    // Command center
    Route::get('/', [OrdDashboardController::class, 'index'])
        ->middleware('page.permission:dashboard,view')
        ->name('dashboard');

    // Orders worklist + 360° detail
    Route::get('/orders', [OrdOrdersController::class, 'index'])
        ->middleware('page.permission:orders,view')
        ->name('orders');
    Route::post('/orders', [OrdOrdersController::class, 'store'])
        ->middleware('page.permission:orders,edit')
        ->name('orders.store');
    Route::get('/orders/{type}/{id}', [OrdOrdersController::class, 'show'])
        ->middleware('page.permission:orders,view')
        ->whereIn('type', ['po', 'so', 'PO', 'SO'])
        ->name('orders.show');
    Route::post('/orders/{type}/{id}/transition', [OrdOrdersController::class, 'transition'])
        ->middleware('page.permission:orders,edit')
        ->whereIn('type', ['po', 'so', 'PO', 'SO'])
        ->name('orders.transition');
    Route::post('/purchase-orders/{id}/generate-so', [OrdOrdersController::class, 'createSalesOrders'])
        ->middleware('page.permission:orders,edit')
        ->name('orders.generate-so');

    // Billing & receipts
    Route::post('/orders/payment', [OrdOrdersController::class, 'updatePayment'])
        ->middleware('page.permission:orders,edit')
        ->name('orders.payment');
    Route::get('/orders/receipt/{type}/{id}', [OrdOrdersController::class, 'downloadReceipt'])
        ->middleware('page.permission:orders,view')
        ->name('orders.download-receipt');

    // Production watch + release to plant floor
    Route::get('/productions', [OrdProductionsController::class, 'index'])
        ->middleware('page.permission:productions,view')
        ->name('productions');
    Route::post('/productions/release/{id}', [OrdProductionsController::class, 'release'])
        ->middleware('page.permission:productions,edit')
        ->name('productions.release');

    // Fulfillment tracking
    Route::get('/delivery', [OrdDeliveryController::class, 'index'])
        ->middleware('page.permission:delivery,view')
        ->name('delivery');
    Route::get('/delivery/{id}/track', [OrdDeliveryController::class, 'track'])
        ->middleware('page.permission:delivery,view')
        ->name('delivery.track');
    Route::post('/delivery/{id}/sync', [OrdDeliveryController::class, 'syncStatus'])
        ->middleware('page.permission:delivery,edit')
        ->name('delivery.sync');

    // Returns (RMA)
    Route::get('/returns', [OrdReturnsController::class, 'index'])
        ->middleware('page.permission:returns,view')
        ->name('returns');
    Route::post('/returns', [OrdReturnsController::class, 'store'])
        ->middleware('page.permission:returns,edit')
        ->name('returns.store');
    Route::post('/returns/{id}/resolve', [OrdReturnsController::class, 'resolve'])
        ->middleware('page.permission:returns,edit')
        ->name('returns.resolve');

});
