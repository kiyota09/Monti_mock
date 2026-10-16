<?php

use App\Http\Controllers\Workforce\AbsentController;
use App\Http\Controllers\Workforce\LeaveController as WorkforceLeaveController;
use App\Http\Controllers\Workforce\SchedulerController;
use App\Http\Controllers\Workforce\WorkforceDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workforce Management Routes
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/workforce')->name('workforce.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [WorkforceDashboardController::class, 'index'])->name('dashboard');
    Route::get('/scheduler', [SchedulerController::class, 'index'])->name('scheduler');
    Route::post('/scheduler/shift', [SchedulerController::class, 'storeShift'])->name('scheduler.shift.store');
    Route::delete('/scheduler/shift/{id}', [SchedulerController::class, 'deleteShift'])->name('scheduler.shift.destroy');
    Route::post('/scheduler/holiday', [SchedulerController::class, 'storeHoliday'])->name('scheduler.holiday.store');
    Route::delete('/scheduler/holiday/{id}', [SchedulerController::class, 'deleteHoliday'])->name('scheduler.holiday.destroy');
    Route::post('/scheduler/shift/bulk', [SchedulerController::class, 'storeBulkShift'])->name('scheduler.shift.bulk');
    Route::patch('/scheduler/holiday/{id}', [SchedulerController::class, 'updateHoliday'])->name('scheduler.holiday.update');
    Route::post('/scheduler/planner', [SchedulerController::class, 'storePlannerEvent'])->name('scheduler.planner.store');
    Route::patch('/scheduler/planner/{id}', [SchedulerController::class, 'updatePlannerEvent'])->name('scheduler.planner.update');
    Route::delete('/scheduler/planner/{id}', [SchedulerController::class, 'deletePlannerEvent'])->name('scheduler.planner.destroy');
    Route::get('/leave', [WorkforceLeaveController::class, 'index'])->name('leave');
    Route::post('/leave/{id}/approve', [WorkforceLeaveController::class, 'approve'])->name('leave.approve');
    Route::post('/leave/{id}/reject', [WorkforceLeaveController::class, 'reject'])->name('leave.reject');
    Route::get('/absent', [AbsentController::class, 'index'])->name('absent');
    Route::post('/absent/{id}/suspend', [AbsentController::class, 'suspend'])->name('absent.suspend');
});