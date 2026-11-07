<?php

use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\ForcedPasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\UnifiedLoginController;
use Illuminate\Support\Facades\Route;

// ✅ Updated to include both web and client guards
// This prevents logged-in clients from being redirected to the employee login
Route::middleware('guest:web,client,supplier,applicant,agency')->group(function () {
    Route::get('login', [UnifiedLoginController::class, 'create'])
        ->name('login');

    Route::post('login', [UnifiedLoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::middleware('guest:web,client')->group(function () {
    // Employee ID Login
    Route::get('employee-login', fn () => redirect()->route('login'))
        ->name('employee.login');

    Route::post('employee-login', fn () => redirect()->route('login'))
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

    // Custom "forgot password" recovery: emails a brand-new password
    // instead of a reset link. Consumed by the login modal.
    Route::post('forgot-password/recover', [PasswordRecoveryController::class, 'employee'])
        ->middleware('throttle:6,1')
        ->name('password.recover');

    Route::post('forgot-password/recover-unified', [PasswordRecoveryController::class, 'unified'])
        ->middleware('throttle:6,1')
        ->name('password.recover.unified');
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

    // Forced password change (first login / after a recovery password).
    Route::post('password/force', [ForcedPasswordController::class, 'employee'])
        ->name('password.force');
});

// Keep the employee logout on the same guarded cleanup path as the portal
// logout controllers, so session and remember-token handling have one owner.
Route::post('logout', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])
    ->name('logout');
