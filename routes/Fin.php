<?php

use App\Http\Controllers\Fin\FinDashboardController;
use App\Http\Controllers\Hrm\InterviewController;
use App\Http\Controllers\Hrm\TraineeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Financial Operations (FIN) Routes
|--------------------------------------------------------------------------
| Dashboards read live ledger tables (see FinanceService) — no hardcoded
| numbers. Record actions require the matching page's edit grant.
| (Top interview/trainee links render HRM pages and are untouched.
| Access control lives in the CEO module + IT Access Control.)
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/fin')->name('fin.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/interview', [InterviewController::class, 'index'])->name('interview.index');
    Route::get('/trainee', [TraineeController::class, 'index'])->name('trainee.index');

    Route::get('/manager', [FinDashboardController::class, 'managerDashboard'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:dashboard,view'])
        ->name('manager.dashboard');
    Route::get('/manager/receivables', [FinDashboardController::class, 'receivables'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:receivables,view'])
        ->name('manager.receivables');
    Route::get('/manager/payables', [FinDashboardController::class, 'payables'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:payables,view'])
        ->name('manager.payables');
    Route::get('/manager/expenses', [FinDashboardController::class, 'expenses'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:expenses,view'])
        ->name('manager.expenses');
    Route::get('/manager/payroll', [FinDashboardController::class, 'payroll'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:payroll,view'])
        ->name('manager.payroll');
    Route::get('/manager/reports', [FinDashboardController::class, 'reports'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:reports,view'])
        ->name('manager.reports');

    // Purchase-order finance approvals (PRO quotations land here).
    Route::get('/manager/approvals', [FinDashboardController::class, 'poApprovals'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:approvals,view'])
        ->name('manager.approvals');
    Route::post('/manager/approvals/{po}/approve', [FinDashboardController::class, 'approvePo'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:approvals,edit'])
        ->name('manager.approvals.approve');
    Route::post('/manager/approvals/{po}/decline', [FinDashboardController::class, 'declinePo'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:approvals,edit'])
        ->name('manager.approvals.decline');

    Route::get('/staff', [FinDashboardController::class, 'staffDashboard'])
        ->middleware(['module.access:FIN', 'position:staff', 'page.permission:dashboard,view'])
        ->name('employee.dashboard');

    // Record actions (live ledger writes)
    Route::post('/manager/receivables/{invoice}/pay', [FinDashboardController::class, 'recordInvoicePayment'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:receivables,edit'])
        ->name('manager.receivables.pay');
    Route::post('/manager/payables/{bill}/pay', [FinDashboardController::class, 'recordBillPayment'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:payables,edit'])
        ->name('manager.payables.pay');
    Route::post('/manager/expenses', [FinDashboardController::class, 'storeExpense'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:dashboard,edit'])
        ->name('manager.expenses.store');
    Route::post('/manager/bills', [FinDashboardController::class, 'storeBill'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:payables,edit'])
        ->name('manager.bills.store');
    Route::post('/manager/budgets', [FinDashboardController::class, 'storeBudget'])
        ->middleware(['module.access:FIN', 'position:manager,staff', 'page.permission:dashboard,edit'])
        ->name('manager.budgets.store');
});
