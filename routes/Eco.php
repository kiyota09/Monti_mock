<?php

use App\Http\Controllers\Crm\CrmInquiryController;
use App\Http\Controllers\Eco\EcoClientController;
use App\Http\Controllers\Eco\EcoCreditController;
use App\Http\Controllers\Eco\EcoDashboardController;
use App\Http\Controllers\Eco\EcoPushController;
use App\Http\Controllers\Eco\EcoStoreController;
use App\Http\Controllers\Eco\EcoSupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| E‑Commerce (ECO) Module
|--------------------------------------------------------------------------
| Every route carries page.permission so explicit per-page view/edit grants
| are enforced. Staff without explicit rows keep full access via the
| middleware's native shortcut.
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/eco')->name('eco.')->middleware(['auth', 'verified', 'module.access:ECO'])->group(function () {
    // Dashboard
    Route::get('/', [EcoDashboardController::class, 'index'])
        ->middleware('page.permission:dashboard,view')->name('dashboard');
    // Store (product catalog)
    Route::get('/store', [EcoStoreController::class, 'index'])
        ->middleware('page.permission:store,view')->name('store');
    // NOTE: client inquiries & conversations moved to the CRM module
    // (dashboard/crm/inquiries) — highly customized orders belong with the
    // relationship workflow, not the storefront.
    // Display-only client directory (information viewing, no chat).
    Route::get('/clients', [EcoClientController::class, 'index'])
        ->middleware('page.permission:client,view')->name('clients');
    Route::get('/clients/{client}/credit-check', [CrmInquiryController::class, 'creditCheck'])
        ->middleware('page.permission:credit,view')->name('credit.check');

    // Credit ledger
    Route::get('/credit', [EcoCreditController::class, 'index'])
        ->middleware('page.permission:credit,view')->name('credit');
    Route::post('/credit/approve/{order}', [EcoCreditController::class, 'approveCreditReview'])
        ->middleware('page.permission:credit,edit')->name('credit.approve');
    // ✅ FIXED: renamed duplicate route to avoid conflict
    Route::post('/credit/approve-order/{order}', [EcoCreditController::class, 'approveOrder'])
        ->middleware('page.permission:credit,edit')->name('credit.approve-order');
    Route::post('/credit/reject/{order}', [EcoCreditController::class, 'rejectOrder'])
        ->middleware('page.permission:credit,edit')->name('credit.reject');
    // Push to SCM / Order Management
    Route::get('/push', [EcoPushController::class, 'index'])
        ->middleware('page.permission:push,view')->name('push');
    // DSS: sustainability preview BEFORE accepting (quick inventory check)
    Route::get('/push/dss/{order}', [EcoPushController::class, 'dssCheck'])
        ->middleware('page.permission:push,view')->name('push.dss');
    // DSS modal per-material "Request" button: file procurement for one
    // shortfall material + notify the PRO module.
    Route::post('/push/dss/{order}/request', [EcoPushController::class, 'requestProcurement'])
        ->middleware('page.permission:push,edit')->name('push.dss.request');
    Route::post('/push/scm/{order}', [EcoPushController::class, 'pushToScm'])
        ->middleware('page.permission:push,edit')->name('push.scm');
    Route::post('/push/order-mgmt/{order}', [EcoPushController::class, 'pushToOrderMgmt'])
        ->middleware('page.permission:push,edit')->name('push.ordermgmt');
    // Access control is centralized in the CEO module (view/request)
    // and IT Access Control (fulfilment).

    // Inside ECO route group
    Route::get('/suppliers', [EcoSupplierController::class, 'index'])
        ->middleware('page.permission:supplier,view')->name('suppliers');
    Route::get('/suppliers/{supplier}/conversation', [EcoSupplierController::class, 'conversation'])
        ->middleware('page.permission:supplier,view')->name('supplier.conversation');
    // Real-time poll feed (new messages since ?after=id, no page reload).
    Route::get('/suppliers/{supplier}/feed', [EcoSupplierController::class, 'feed'])
        ->middleware('page.permission:supplier,view')->name('supplier.feed');
    Route::post('/suppliers/{supplier}/message', [EcoSupplierController::class, 'sendMessage'])
        ->middleware('page.permission:supplier,edit')->name('supplier.message');
    Route::post('/suppliers/{supplier}/meeting', [EcoSupplierController::class, 'setMeeting'])
        ->middleware('page.permission:supplier,edit')->name('supplier.meeting');
    Route::get('/suppliers/{supplier}/credit-check', [EcoSupplierController::class, 'creditCheck'])
        ->middleware('page.permission:supplier,view')->name('supplier.credit-check');
    Route::post('/suppliers/{supplier}/request', [EcoSupplierController::class, 'sendRequest'])
        ->middleware('page.permission:supplier,edit')->name('supplier.request');

    Route::post('/push/manual-po', [EcoPushController::class, 'manualStore'])
        ->middleware('page.permission:push,edit')->name('po.manual_store');
    Route::post('/push/manual-jo', [EcoPushController::class, 'manualJobOrder'])
        ->middleware('page.permission:push,edit')->name('jo.manual_store');
});
