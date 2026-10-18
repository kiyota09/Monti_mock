<?php

use App\Http\Controllers\Agency\AgencyAuthController;
use App\Http\Controllers\Auth\ForcedPasswordController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Agency\AgencyDashboardController;
use App\Http\Controllers\Agency\AttendancePortalController;
use App\Http\Controllers\Agency\BillingPortalController;
use App\Http\Controllers\Agency\PasswordController;
use App\Http\Controllers\Agency\RequestPortalController;
use App\Http\Controllers\Agency\WorkerPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agency Gateway (auth + agency portal)
|--------------------------------------------------------------------------
*/
Route::middleware('guest:agency')->group(function () {
    Route::get('agency/login', [AgencyAuthController::class, 'showLogin'])->name('agency.login');
    Route::post('agency/login', [AgencyAuthController::class, 'login'])->middleware('throttle:10,1')->name('agency.login.store');
    Route::post('agency/password/recover', [PasswordRecoveryController::class, 'agency'])->middleware('throttle:6,1')->name('agency.password.recover');
});

Route::post('agency/logout', [AgencyAuthController::class, 'logout'])
    ->middleware('auth:agency')
    ->name('agency.logout');

Route::middleware('auth:agency')->prefix('agency')->name('agency.')->group(function () {
    Route::get('/dashboard', [AgencyDashboardController::class, 'index'])->name('dashboard');

    // Forced password change at first login.
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');
    // Forced password change via the global modal (no current password needed).
    Route::post('/password/force', [ForcedPasswordController::class, 'agency'])->name('password.force');

    // Workers (own agency only).
    Route::get('/workers', [WorkerPortalController::class, 'index'])->name('workers.index');
    Route::get('/workers/create', [WorkerPortalController::class, 'create'])->name('workers.create');
    Route::post('/workers', [WorkerPortalController::class, 'store'])->name('workers.store');
    Route::get('/workers/{worker}/edit', [WorkerPortalController::class, 'edit'])->name('workers.edit');
    Route::put('/workers/{worker}', [WorkerPortalController::class, 'update'])->name('workers.update');
    Route::delete('/workers/{worker}/documents/{document}', [WorkerPortalController::class, 'destroyDocument'])->name('workers.documents.destroy');

    // Requests (read-only + proposals).
    Route::get('/requests', [RequestPortalController::class, 'index'])->name('requests.index');
    Route::get('/requests/{manpowerRequest}', [RequestPortalController::class, 'show'])->name('requests.show');
    Route::post('/requests/{manpowerRequest}/propose', [RequestPortalController::class, 'propose'])->name('requests.propose');

    // Attendance (read-only + confirm/dispute).
    Route::get('/attendance', [AttendancePortalController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/{attendance}/confirm', [AttendancePortalController::class, 'confirm'])->name('attendance.confirm');
    Route::post('/attendance/{attendance}/dispute', [AttendancePortalController::class, 'dispute'])->name('attendance.dispute');

    // Billing (Issued/Paid only, read-only + exports).
    Route::get('/billing', [BillingPortalController::class, 'index'])->name('billing.index');
    Route::get('/billing/{statement}', [BillingPortalController::class, 'show'])->name('billing.show');
    Route::get('/billing/{statement}/export-pdf', [BillingPortalController::class, 'exportPdf'])->name('billing.export-pdf');
    Route::get('/billing/{statement}/export-excel', [BillingPortalController::class, 'exportExcel'])->name('billing.export-excel');
});
