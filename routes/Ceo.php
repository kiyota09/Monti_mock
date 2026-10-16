<?php

use App\Http\Controllers\Ceo\CeoAccessController;
use App\Http\Controllers\Ceo\CeoApprovalController;
use App\Http\Controllers\Ceo\CeoAuditController;
use App\Http\Controllers\Ceo\CeoDeliveriesController;
use App\Http\Controllers\Ceo\CeoGoalController;
use App\Http\Controllers\Ceo\CeoNotificationController;
use App\Http\Controllers\Ceo\CeoDashboardController;
use App\Http\Controllers\Ceo\CeoReportsController;
use App\Http\Controllers\Ceo\CeoTraceabilityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CEO Dashboard Routes
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/ceo')->name('ceo.')->middleware(['auth', 'verified', 'role:CEO'])->group(function () {
    Route::get('/', [CeoDashboardController::class, 'index'])->name('dashboard');

    // Access Control — VIEW + REQUEST ONLY (executive office).
    // Direct writes (promote/demote/modules/pages/roles/photo/clients)
    // are disabled in CeoAccessController and now belong to IT.
    Route::get('/access', [CeoAccessController::class, 'index'])->name('access');
    Route::get('/access/employee/{id}/personal-info', [CeoAccessController::class, 'getEmployeePersonalInfo'])->name('access.employeePersonalInfo');

    // Executive command → IT Access Control notifications.
    Route::post('/access/request-position', [CeoAccessController::class, 'requestPosition'])->name('access.requestPosition');
    Route::post('/access/requests/{id}/cancel', [CeoAccessController::class, 'cancelRequest'])->name('access.cancelRequest');

    Route::post('/access/update-position', [CeoAccessController::class, 'updatePosition'])->name('access.updatePosition');
    Route::post('/access/update-modules', [CeoAccessController::class, 'updateModules'])->name('access.updateModules');
    Route::post('/access/update-staff-pages', [CeoAccessController::class, 'updateStaffPages'])->name('access.updateStaffPages');
    Route::post('/access/assign-staff-role', [CeoAccessController::class, 'assignStaffRole'])->name('access.assignStaffRole');
    Route::post('/access/update-profile-photo', [CeoAccessController::class, 'updateProfilePhoto'])->name('access.updateProfilePhoto');
    Route::get('/access/client-assignments/{staffId}', [CeoAccessController::class, 'getClientAssignments'])->name('access.clientAssignments');
    Route::post('/access/assign-clients', [CeoAccessController::class, 'updateClientAssignments'])->name('access.updateClientAssignments');

    // Executive reports (cross-module KPIs + trends, read-only)
    Route::get('/reports', [CeoReportsController::class, 'index'])->name('reports');

    // Deliveries tracking (outbound: client, truck, driver, POD, payment)
    Route::get('/deliveries', [CeoDeliveriesController::class, 'index'])->name('deliveries');

    // Traceability (raw-lot → finished-product genealogy, read-only)
    Route::get('/traceability', [CeoTraceabilityController::class, 'index'])->name('traceability');

    // Approvals Center (the President's action arm: approve/reject here
    // instead of editing inside modules). POST actions stay President-only.
    Route::post('/approvals/payroll/{payroll}/approve', [CeoApprovalController::class, 'approvePayroll'])->name('approvals.payroll.approve');
    Route::post('/approvals/payroll/{payroll}/reject', [CeoApprovalController::class, 'rejectPayroll'])->name('approvals.payroll.reject');
    Route::post('/approvals/vendor/{registration}/approve', [CeoApprovalController::class, 'approveVendor'])->name('approvals.vendor.approve');
    Route::post('/approvals/vendor/{registration}/reject', [CeoApprovalController::class, 'rejectVendor'])->name('approvals.vendor.reject');
    Route::post('/approvals/credit/{order}/approve', [CeoApprovalController::class, 'approveCredit'])->name('approvals.credit.approve');
    Route::post('/approvals/credit/{order}/reject', [CeoApprovalController::class, 'rejectCredit'])->name('approvals.credit.reject');

    // Global audit trail (read-only)
    Route::get('/audit', [CeoAuditController::class, 'index'])->name('audit');

    // Notifications inbox (push-style executive alerts)
    Route::get('/inbox', [CeoNotificationController::class, 'index'])->name('inbox');
    Route::patch('/inbox/{notification}/read', [CeoNotificationController::class, 'markRead'])->name('inbox.read');
    Route::post('/inbox/read-all', [CeoNotificationController::class, 'markAllRead'])->name('inbox.read-all');

    // Board pack (print-ready monthly bundle)
    Route::get('/board-pack', [CeoReportsController::class, 'boardPack'])->name('board-pack');

    // Goals & targets (actuals computed from module data)
    Route::get('/goals', [CeoGoalController::class, 'index'])->name('goals');
    Route::post('/goals', [CeoGoalController::class, 'store'])->name('goals.store');
    Route::delete('/goals/{goal}', [CeoGoalController::class, 'destroy'])->name('goals.destroy');
});

// Joint Approvals (read-only view shared by President + COO/VP).
// Kept outside the CEO-only group so role:COO passes; the approve/reject
// POSTs above stay President-only.
Route::get('/dashboard/ceo/approvals', [CeoApprovalController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:CEO,COO'])->name('ceo.approvals');