<?php

use App\Http\Controllers\Auth\SupplierAuthController;
use App\Http\Controllers\Suppliers\SupplierChatController;
use App\Http\Controllers\Suppliers\SupplierDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vendor/Supplier Gateway Authentication Routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest:supplier')->group(function () {
    Route::get('supplier/login', [SupplierAuthController::class, 'showLogin'])->name('supplier.login');
    Route::post('supplier/login', [SupplierAuthController::class, 'login'])->middleware('throttle:10,1')->name('supplier.login.store');
    Route::get('supplier/register', [SupplierAuthController::class, 'create'])->name('supplier.register');
    Route::post('supplier/register', [SupplierAuthController::class, 'store'])->middleware('throttle:6,1')->name('supplier.register.store');
});

Route::post('supplier/logout', [SupplierAuthController::class, 'logout'])
    ->middleware('auth:supplier')
    ->name('supplier.logout');

/*
|--------------------------------------------------------------------------
| Protected Supplier Portal Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:supplier')->prefix('supplier')->name('supplier.')->group(function () {
    Route::get('/dashboard', [SupplierDashboardController::class, 'index'])->name('dashboard');
    Route::post('/rfq/{id}/respond', [SupplierDashboardController::class, 'submitQuotation'])->name('rfq.respond');
    Route::get('/orders', [SupplierDashboardController::class, 'purchaseOrders'])->name('orders');
    Route::post('/orders/{id}/status', [SupplierDashboardController::class, 'updateOrderStatus'])->name('orders.update_status');
    Route::post('/orders/{id}/invoice', [SupplierDashboardController::class, 'createInvoice'])->name('orders.invoice');
    // Product catalog: raw materials this supplier carries.
    Route::get('/products', [SupplierDashboardController::class, 'products'])->name('products');
    Route::post('/products', [SupplierDashboardController::class, 'storeProduct'])->name('products.store');
    Route::patch('/products/{product}/toggle', [SupplierDashboardController::class, 'toggleProduct'])->name('products.toggle');
    Route::delete('/products/{product}', [SupplierDashboardController::class, 'destroyProduct'])->name('products.destroy');
    // Two-way chat with ECO (same supplier_messages thread, sender_type=supplier).
    Route::get('/messages', [SupplierChatController::class, 'index'])->name('messages');
    Route::get('/chat/feed', [SupplierChatController::class, 'feed'])->name('chat.feed');
    Route::post('/chat', [SupplierChatController::class, 'send'])->name('chat.send');
});