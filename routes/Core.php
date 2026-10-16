<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\Hrm\ApplicantController as HrmApplicantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Trainee\TraineeAttendanceController;
use App\Http\Controllers\Trainee\TraineePayslipController;
use App\Http\Controllers\Trainee\TraineeTimeKeepingController;
use App\Http\Controllers\Users\AppController;
use App\Http\Controllers\Users\ClockController;
use App\Http\Controllers\Users\LeaveController as UserLeaveController;
use App\Http\Controllers\Users\MessengerController;
use App\Http\Controllers\Users\TopbarController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return inertia('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => app()->version(),
        'phpVersion' => PHP_VERSION,
    ]);
});

/*
|--------------------------------------------------------------------------
| Public Career Application Routes
|--------------------------------------------------------------------------
*/
Route::get('/apply', function () {
    return inertia('Auth/apply');
})->name('apply');

Route::post('/apply/store', [HrmApplicantController::class, 'store'])->name('applicants.public.store');

/*
|--------------------------------------------------------------------------
| Authenticated User Core Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Shown when every page permission is still 'disabled' (IT default).
    Route::get('/awaiting-access', function () {
        return inertia('Dashboard/AwaitingAccess', ['user' => auth()->user()]);
    })->name('awaiting.access');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    // TopBar Settings → Help Center (FAQs + Report a Problem) and About.
    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::post('/help/report', [HelpController::class, 'store'])->name('help.report.store');
    Route::get('/help/report/{report}', [HelpController::class, 'show'])->name('help.report.show');
    Route::get('/about', [HelpController::class, 'about'])->name('about.index');
    Route::post('/profile/application', [ProfileController::class, 'updateApplication'])->name('profile.application.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |----------------------------------------------------------------------
    | Universal top bar (all internal roles) — JSON endpoints for the
    | notification feed, published memos, user directory and messenger.
    | Polling-based; no socket server required.
    |----------------------------------------------------------------------
    */
    Route::prefix('/topbar')->name('topbar.')->group(function () {
        Route::get('/summary', [TopbarController::class, 'summary'])->name('summary');
        Route::get('/search', [TopbarController::class, 'search'])->name('search');
        Route::get('/notifications', [TopbarController::class, 'notifications'])->name('notifications');
        Route::patch('/notifications/{notification}/read', [TopbarController::class, 'readNotification'])->name('notifications.read');
        Route::post('/notifications/read-all', [TopbarController::class, 'readAllNotifications'])->name('notifications.read-all');
        Route::get('/memos', [TopbarController::class, 'memos'])->name('memos');
        Route::get('/directory', [TopbarController::class, 'directory'])->name('directory');

        Route::get('/threads', [MessengerController::class, 'threads'])->name('threads');
        Route::post('/threads', [MessengerController::class, 'store'])->name('threads.store');
        Route::get('/threads/{thread}', [MessengerController::class, 'show'])->name('threads.show');
        Route::post('/threads/{thread}/send', [MessengerController::class, 'send'])->name('threads.send');
        Route::patch('/threads/{thread}', [MessengerController::class, 'update'])->name('threads.update');
        Route::post('/threads/{thread}/members', [MessengerController::class, 'addMembers'])->name('threads.members.add');
        Route::delete('/threads/{thread}/members/{user}', [MessengerController::class, 'removeMember'])->name('threads.members.remove');
        Route::post('/threads/{thread}/leave', [MessengerController::class, 'leave'])->name('threads.leave');
    });
});

/*
|--------------------------------------------------------------------------
| Unified Employee UI Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'position:staff,manager'])->prefix('dashboard/employee-ui')->group(function () {
    Route::get('/', [AppController::class, 'index'])->name('employee.ui.dashboard');
    Route::get('/clock', [ClockController::class, 'clock'])->name('employee.ui.clock');
    Route::post('/clock/toggle', [ClockController::class, 'toggle'])
        ->middleware('geofence')
        ->name('employee.attendance.toggle');
    Route::get('/leave', [UserLeaveController::class, 'leave'])->name('employee.ui.leave');
    Route::post('/leave', [UserLeaveController::class, 'store'])->name('employee.leave.store');
    Route::get('/payslip', function () {
        return inertia('Dashboard/USERS/payslip');
    })->name('employee.ui.payslip');
});

/*
|--------------------------------------------------------------------------
| Unified Trainee Portal Routes
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard/trainee')->middleware(['auth', 'verified', 'position:trainee'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('trainee.dashboard');
    Route::get('/timekeeping', [TraineeTimeKeepingController::class, 'index'])->name('trainee.timekeeping');
    Route::post('/timekeeping/clock', [TraineeTimeKeepingController::class, 'clockInOut'])->name('trainee.timekeeping.clock');
    Route::get('/attendance', [TraineeAttendanceController::class, 'index'])->name('trainee.attendance');
    Route::get('/payslip', [TraineePayslipController::class, 'index'])->name('trainee.payslip');
    Route::get('/payslip/{payroll}', [TraineePayslipController::class, 'show'])->name('trainee.payslip.show');
});