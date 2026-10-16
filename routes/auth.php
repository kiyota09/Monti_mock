<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\EmployeeLoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // Added for custom logout logic
use Illuminate\Support\Facades\Route;

// ✅ Updated to include both web and client guards
// This prevents logged-in clients from being redirected to the employee login
Route::middleware('guest:web,client')->group(function () {
    // Existing Email/Password Login (Employees)
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    // Employee ID Login
    Route::get('employee-login', [EmployeeLoginController::class, 'create'])
        ->name('employee.login');

    Route::post('employee-login', [EmployeeLoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('employee.login.store');

    // Registration (Internal Employees)
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register.store');

    // Password Reset
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.store');
});

// ✅ Updated to support both guards for authenticated actions
Route::middleware('auth:web,client')->group(function () {
    // Email Verification
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Password Confirmation
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    // Password Update
    Route::put('password', [PasswordController::class, 'update'])
        ->name('password.update');

    // ✅ Unified Logout Logic
    // Logs out of EVERY guard (web, client, supplier, applicant). Logging
    // out a single guard leaves the other guards' remember-me cookies alive,
    // so the next request silently re-authenticates and the user appears to
    // still be logged in ("log out twice" bug).
    Route::post('logout', function (Request $request) {
        foreach (['web', 'client', 'supplier', 'applicant'] as $guard) {
            Auth::guard($guard)->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    })->name('logout');
});
