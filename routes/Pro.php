<?php

use App\Http\Controllers\Hrm\InterviewController;
use App\Http\Controllers\Hrm\TraineeController;
use App\Http\Controllers\Pro\ProcurementController;
use App\Http\Controllers\Pro\ProDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Procurement Sub-System (PRO) Routes
|--------------------------------------------------------------------------
| Manager-group routes carry page.permission so explicit per-page view/edit
| grants are enforced. Managers without explicit rows keep full access via
| the middleware's native shortcut. (Top interview/trainee links
| render HRM pages and are intentionally untouched. Access control lives
| in the CEO module + IT Access Control.)
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/pro')->name('pro.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/interview', [InterviewController::class, 'index'])->name('interview.index');
    Route::get('/trainee', [TraineeController::class, 'index'])->name('trainee.index');

    Route::middleware(['module.access:PRO', 'position:manager'])->prefix('manager')->name('manager.')->group(function () {
        Route::get('/', [ProDashboardController::class, 'managerDashboard'])
            ->middleware('page.permission:dashboard,view')->name('dashboard');
        Route::get('/material-requests', [ProcurementController::class, 'materialRequests'])
            ->middleware('page.permission:requests,view')->name('material-requests');
        Route::post('/material-requests/rfq', [ProcurementController::class, 'createRFQ'])
            ->middleware('page.permission:requests,edit')->name('rfq.store');
        Route::get('/supplier-quotations', [ProcurementController::class, 'supplierQuotations'])
            ->middleware('page.permission:quotations,view')->name('supplier-quotations');
        Route::post('/quotations/{responseId}/accept', [ProcurementController::class, 'acceptQuotation'])
            ->middleware('page.permission:quotations,edit')->name('quotations.accept');
        Route::post('/quotations/{responseId}/decline', [ProcurementController::class, 'declineQuotation'])
            ->middleware('page.permission:quotations,edit')->name('quotations.decline');
        Route::get('/receipt', [ProcurementController::class, 'receipt'])
            ->middleware('page.permission:receipt,view')->name('receipt');
        Route::get('/tracking', [ProcurementController::class, 'tracking'])
            ->middleware('page.permission:tracking,view')->name('tracking');
        Route::post('/purchase-orders/{poId}/send', [ProcurementController::class, 'sendPurchaseOrder'])
            ->middleware('page.permission:receipt,edit')->name('purchase-orders.send');
        Route::post('/purchase-orders/{poId}/return', [ProcurementController::class, 'returnToRequests'])
            ->middleware('page.permission:receipt,edit')->name('purchase-orders.return');
        Route::post('/invoices/{invoiceId}/pay', [ProcurementController::class, 'payInvoice'])
            ->middleware('page.permission:receipt,edit')->name('invoices.pay');
    });
});
