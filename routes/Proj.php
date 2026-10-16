<?php

use App\Http\Controllers\Hrm\InterviewController;
use App\Http\Controllers\Hrm\TraineeController;
use App\Http\Controllers\Proj\ProjDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Project Automation (PROJ) Routes
|--------------------------------------------------------------------------
| (Top interview/trainee links render HRM pages. Access control lives
| in the CEO module + IT Access Control.)
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/proj')->name('proj.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/interview', [InterviewController::class, 'index'])->name('interview.index');
    Route::get('/trainee', [TraineeController::class, 'index'])->name('trainee.index');

    Route::get('/manager', [ProjDashboardController::class, 'managerDashboard'])
        ->middleware(['module.access:PROJ', 'position:manager'])
        ->name('manager.dashboard');

    Route::get('/staff', [ProjDashboardController::class, 'staffDashboard'])
        ->middleware(['module.access:PROJ', 'position:staff'])
        ->name('employee.dashboard');
});