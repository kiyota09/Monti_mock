<?php

use App\Http\Controllers\Inv\BomController;
use App\Http\Controllers\Inv\CheckerController;
use App\Http\Controllers\Inv\InvDashboardController;
use App\Http\Controllers\Inv\MaterialController;
use App\Http\Controllers\Inv\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory Module
|--------------------------------------------------------------------------
| Every route carries page.permission so explicit per-page view/edit grants
| are enforced. Staff without explicit rows keep full access via the
| middleware's native shortcut.
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/inventory')->name('inv.')->middleware(['auth', 'verified', 'module.access:INV'])->group(function () {
    Route::get('/', [InvDashboardController::class, 'managerDashboard'])
        ->middleware('page.permission:dashboard,view')->name('dashboard');
    Route::get('/materials', [MaterialController::class, 'material'])
        ->middleware('page.permission:materials,view')->name('materials');
    Route::post('/materials', [MaterialController::class, 'store'])
        ->middleware('page.permission:materials,edit')->name('materials.store');
    Route::delete('/materials/{id}', [MaterialController::class, 'destroy'])
        ->middleware('page.permission:materials,edit')->name('materials.destroy');

    Route::get('/products', [ProductController::class, 'product'])
        ->middleware('page.permission:products,view')->name('products');
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('page.permission:products,edit')->name('products.store');
    Route::post('/products/{id}', [ProductController::class, 'update'])
        ->middleware('page.permission:products,edit')->name('products.update');
    Route::delete('/products/{id}', [ProductController::class, 'destroy'])
        ->middleware('page.permission:products,edit')->name('products.destroy');
    Route::delete('/products/image/{imageId}', [ProductController::class, 'destroyImage'])
        ->middleware('page.permission:products,edit')->name('products.image.destroy');

    Route::get('/bom', [BomController::class, 'index'])
        ->middleware('page.permission:bom,view')->name('bom');
    Route::post('/bom', [BomController::class, 'store'])
        ->middleware('page.permission:bom,edit')->name('bom.store');
    Route::put('/bom/{id}', [BomController::class, 'update'])
        ->middleware('page.permission:bom,edit')->name('bom.update');
    Route::delete('/bom/{id}', [BomController::class, 'destroy'])
        ->middleware('page.permission:bom,edit')->name('bom.destroy');

    Route::get('/checker', [CheckerController::class, 'index'])
        ->middleware('page.permission:checker,view')->name('checker');
    // ✅ FIXED: Use MaterialController@procurement for creating procurement requests
    Route::post('/checker/procurement-bulk', [MaterialController::class, 'bulkProcurement'])
        ->middleware('page.permission:checker,edit')->name('checker.procurement.bulk');
    Route::post('/checker/procurement/{material}', [MaterialController::class, 'procurement'])
        ->middleware('page.permission:checker,edit')->name('checker.procurement');
    Route::post('/checker/order/{order}', [CheckerController::class, 'checkOrder'])
        ->middleware('page.permission:checker,edit')->name('checker.order');

    Route::get('/product', [ProductController::class, 'product'])
        ->middleware('page.permission:products,view')->name('manager.product');
    Route::post('/product', [ProductController::class, 'store'])
        ->middleware('page.permission:products,edit')->name('manager.product.store');
    Route::post('/product/{id}/update', [ProductController::class, 'update'])
        ->middleware('page.permission:products,edit')->name('manager.product.update');
    Route::delete('/product/image/{imageId}', [ProductController::class, 'destroyImage'])
        ->middleware('page.permission:products,edit')->name('manager.product.image.destroy');
    Route::delete('/product/{id}', [ProductController::class, 'destroy'])
        ->middleware('page.permission:products,edit')->name('manager.product.destroy');

    // Edit view for material
    Route::patch('/materials/{material}', [MaterialController::class, 'update'])
        ->middleware('page.permission:materials,edit')->name('materials.update');
});
