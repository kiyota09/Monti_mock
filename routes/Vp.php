<?php

use App\Http\Controllers\Vp\VpBriefsController;
use App\Http\Controllers\Vp\VpDirectiveController;
use App\Http\Controllers\Vp\VpOperationsController;
use App\Http\Controllers\Vp\VpWorkforceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vice Presidency (VP) — Chief Operating Officer workspace
|--------------------------------------------------------------------------
| The VP holds role COO (position vice_president) and sees this module;
| the President (role CEO) keeps oversight access through the CEO bypass
| in CheckRole, while all other roles are denied.
*/
Route::prefix('dashboard/vp')->name('vp.')->middleware(['auth', 'verified', 'role:COO'])->group(function () {

    // Operations Command (daily plant pulse, read-only)
    Route::get('/operations', [VpOperationsController::class, 'index'])->name('operations');

    // Directives Tracker (action items to departments)
    Route::get('/directives', [VpDirectiveController::class, 'index'])->name('directives');
    Route::post('/directives', [VpDirectiveController::class, 'store'])->name('directives.store');
    Route::patch('/directives/{directive}', [VpDirectiveController::class, 'update'])->name('directives.update');
    Route::delete('/directives/{directive}', [VpDirectiveController::class, 'destroy'])->name('directives.destroy');

    // Workforce Overview (read-only aggregates)
    Route::get('/workforce', [VpWorkforceController::class, 'index'])->name('workforce');

    // Downtime & Maintenance Board (read-only)
    Route::get('/downtime', [VpBriefsController::class, 'downtime'])->name('downtime');

    // Shift Handovers (continuity log, read-only here)
    Route::get('/handovers', [VpBriefsController::class, 'handovers'])->name('handovers');

    // Production Plan vs Actual (read-only)
    Route::get('/plan', [VpBriefsController::class, 'plan'])->name('plan');

    // Utilities & Boiler Brief (read-only)
    Route::get('/utilities', [VpBriefsController::class, 'utilities'])->name('utilities');

    // Operational Bulletins (VP broadcasts)
    Route::get('/bulletins', [VpBriefsController::class, 'bulletins'])->name('bulletins');
    Route::post('/bulletins', [VpBriefsController::class, 'storeBulletin'])->name('bulletins.store');
    Route::delete('/bulletins/{bulletin}', [VpBriefsController::class, 'destroyBulletin'])->name('bulletins.destroy');
});
