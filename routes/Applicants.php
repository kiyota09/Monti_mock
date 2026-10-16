<?php

use App\Http\Controllers\Applicants\ApplicantController as ApplicantsModuleController;
use App\Http\Controllers\Applicants\ApplicantDocumentController as ApplicantsDocumentController;
use App\Http\Controllers\Applicants\Portal\ApplicationsController as PortalApplicationsController;
use App\Http\Controllers\Applicants\Portal\DashboardController as PortalDashboardController;
use App\Http\Controllers\Applicants\Portal\JobsController as PortalJobsController;
use App\Http\Controllers\Applicants\Portal\NotificationsController as PortalNotificationsController;
use App\Http\Controllers\Applicants\Portal\OnboardingController as PortalOnboardingController;
use App\Http\Controllers\Applicants\Portal\ProfileController as PortalProfileController;
use App\Http\Controllers\Auth\ApplicantAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Applicant Portal Auth (public, mirrors client/supplier portals)
|--------------------------------------------------------------------------
*/
Route::middleware('guest:applicant')->group(function () {
    Route::get('applicant/register', [ApplicantAuthController::class, 'create'])->name('applicant.register');
    Route::post('applicant/register', [ApplicantAuthController::class, 'store'])->middleware('throttle:6,1')->name('applicant.register.store');
    Route::get('applicant/verify-otp', [ApplicantAuthController::class, 'showVerifyForm'])->name('applicant.verify-otp.show');
    Route::post('applicant/verify-otp', [ApplicantAuthController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('applicant.verify-otp.store');
    Route::post('applicant/resend-otp', [ApplicantAuthController::class, 'resendOtp'])->middleware('throttle:6,1')->name('applicant.resend-otp');
    Route::get('applicant/login', [ApplicantAuthController::class, 'showLogin'])->name('applicant.login');
    Route::post('applicant/login', [ApplicantAuthController::class, 'login'])->middleware('throttle:10,1')->name('applicant.login.store');
    Route::post('applicant/reactivate', [PortalProfileController::class, 'reactivate'])->middleware('throttle:6,1')->name('applicant.reactivate');
});

Route::post('applicant/logout', [ApplicantAuthController::class, 'logout'])
    ->middleware('auth:applicant')
    ->name('applicant.logout');

Route::middleware('auth:applicant')->prefix('applicant')->name('applicant.')->group(function () {
    // Official APPLICANTS portal (resources/js/Pages/Dashboard/APPLICANTS/*)
    Route::get('/dashboard', [PortalDashboardController::class, 'index'])->name('dashboard');

    Route::get('/jobs', [PortalJobsController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/search', [PortalJobsController::class, 'search'])->name('jobs.search');
    Route::post('/jobs/{job}/apply', [PortalJobsController::class, 'apply'])->name('jobs.storeApplication');

    Route::get('/applications', [PortalApplicationsController::class, 'index'])->name('applications.index');
    Route::get('/applications/{application}', [PortalApplicationsController::class, 'show'])->name('applications.show');
    Route::post('/applications/{application}/withdraw', [PortalApplicationsController::class, 'withdraw'])->name('applications.withdraw');
    Route::get('/applications/{application}/status', [PortalApplicationsController::class, 'applicationStatus'])->name('applications.status');

    Route::get('/interviews', [PortalApplicationsController::class, 'interviews'])->name('interviews');
    Route::post('/interviews/{interview}/confirm', [PortalApplicationsController::class, 'confirmInterview'])->name('interviews.confirm');
    Route::post('/interviews/{interview}/reschedule', [PortalApplicationsController::class, 'rescheduleInterview'])->name('interviews.reschedule');
    Route::post('/interviews/{interview}/cancel', [PortalApplicationsController::class, 'cancelInterview'])->name('interviews.cancel');

    Route::get('/notifications', [PortalNotificationsController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{tab}', [PortalNotificationsController::class, 'tab'])
        ->where('tab', 'all|unread|read')->name('notifications.tab');
    Route::get('/notifications-count', [PortalNotificationsController::class, 'count'])->name('notifications.count');
    Route::get('/notifications/{notification}', [PortalNotificationsController::class, 'show'])->name('notifications.show');
    Route::get('/notifications/{notification}/details', [PortalNotificationsController::class, 'details'])->name('notifications.details');
    Route::post('/notifications/mark-all-read', [PortalNotificationsController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::delete('/notifications/delete-all', [PortalNotificationsController::class, 'destroyAll'])->name('notifications.delete-all');
    Route::post('/notifications/{notification}/mark-read', [PortalNotificationsController::class, 'markRead'])->name('notifications.mark-read');
    Route::delete('/notifications/{notification}', [PortalNotificationsController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/onboarding', [PortalOnboardingController::class, 'index'])->name('onboarding.index');
    Route::get('/onboarding/{onboarding}', [PortalOnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/onboarding/items/{item}/submit', [PortalOnboardingController::class, 'submitItem'])->name('onboarding.items.submit');
    Route::post('/onboarding/activities/{activity}/attend', [PortalOnboardingController::class, 'attendActivity'])->name('onboarding.activities.attend');

    Route::get('/profile', [PortalProfileController::class, 'index'])->name('profile.index');
    Route::post('/profile/update', [PortalProfileController::class, 'update'])->name('profile.update');

    Route::get('/account', [PortalProfileController::class, 'account'])->name('account.index');
    Route::get('/account/activity', [PortalProfileController::class, 'activity'])->name('account.activity');
    Route::post('/account/email', [PortalProfileController::class, 'updateEmail'])->name('account.email.update');
    Route::post('/account/password', [PortalProfileController::class, 'password'])->name('account.password.update');
    Route::post('/account/verify-email', [PortalProfileController::class, 'verify'])->name('account.email.verify');
    Route::post('/account/deactivate', [PortalProfileController::class, 'deactivate'])->name('account.deactivate');
});

/*
|--------------------------------------------------------------------------
| Applicants Module (connected to HRM recruitment)
|--------------------------------------------------------------------------
| Dedicated applicant master. Permission mapping reuses the canonical HRM
| `application` page so IT grants, AccessGate and the HRM sidebar keep
| working — no new role required. HRM_NEW/Applications.vue is shared.
*/
Route::prefix('dashboard/applicants')->name('applicants.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [ApplicantsModuleController::class, 'index'])
        ->middleware('page.permission:application,view')
        ->name('index');
    Route::post('/', [ApplicantsModuleController::class, 'store'])
        ->middleware('page.permission:application,edit')
        ->name('store');
    Route::patch('/{applicant}', [ApplicantsModuleController::class, 'update'])
        ->middleware('page.permission:application,edit')
        ->name('update');
    Route::post('/{applicant}/accept', [ApplicantsModuleController::class, 'accept'])
        ->middleware('page.permission:application,edit')
        ->name('accept');
    Route::post('/{applicant}/send-to-interview', [ApplicantsModuleController::class, 'sendToInterview'])
        ->middleware('page.permission:application,edit')
        ->name('send-to-interview');
    Route::post('/{applicant}/reject', [ApplicantsModuleController::class, 'reject'])
        ->middleware('page.permission:application,edit')
        ->name('reject');
    Route::post('/{applicant}/restore', [ApplicantsModuleController::class, 'restore'])
        ->middleware('page.permission:application,edit')
        ->name('restore');
    Route::post('/{applicant}/hire', [ApplicantsModuleController::class, 'hire'])
        ->middleware('page.permission:application,edit')
        ->name('hire');

    Route::post('/{applicant}/documents', [ApplicantsDocumentController::class, 'store'])
        ->middleware('page.permission:application,edit')
        ->name('documents.store');
    Route::post('/{applicant}/documents/{document}/verify', [ApplicantsDocumentController::class, 'verify'])
        ->middleware('page.permission:application,edit')
        ->name('documents.verify');
    Route::delete('/{applicant}/documents/{document}', [ApplicantsDocumentController::class, 'destroy'])
        ->middleware('page.permission:application,edit')
        ->name('documents.destroy');
});

// Raw-URL alias for the shared profile payload (same shape as
// /hrm/recruitment/applications/{id}/profile).
Route::prefix('applicants')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/{applicant}/profile', [ApplicantsModuleController::class, 'profile'])
        ->middleware('page.permission:application,view');
});
