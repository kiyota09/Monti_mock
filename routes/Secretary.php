<?php

use App\Http\Controllers\Sec\SecretaryDashboardController;
use App\Http\Controllers\Sec\SecretaryDocumentController;
use App\Http\Controllers\Sec\SecretaryMeetingController;
use App\Http\Controllers\Sec\SecretaryMemoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Secretary Workspace (SEC)
|--------------------------------------------------------------------------
| Executive-office pages for the company secretary, mirroring the CEO
| pattern: position-gated (secretary-only, CEO bypass via
| the position middleware) — not module-gated.
*/
Route::prefix('dashboard/secretary')->name('secretary.')->middleware(['auth', 'verified', 'position:secretary'])->group(function () {

    Route::get('/', [SecretaryDashboardController::class, 'index'])->name('dashboard');

    // Document & correspondence control registry
    Route::get('/documents', [SecretaryDocumentController::class, 'index'])->name('documents');
    Route::post('/documents', [SecretaryDocumentController::class, 'store'])->name('documents.store');
    Route::patch('/documents/{document}/status', [SecretaryDocumentController::class, 'updateStatus'])->name('documents.status');
    Route::delete('/documents/{document}', [SecretaryDocumentController::class, 'destroy'])->name('documents.destroy');

    // Meeting & appointment scheduler
    Route::get('/meetings', [SecretaryMeetingController::class, 'index'])->name('meetings');
    Route::post('/meetings', [SecretaryMeetingController::class, 'store'])->name('meetings.store');
    Route::patch('/meetings/{meeting}', [SecretaryMeetingController::class, 'update'])->name('meetings.update');
    Route::delete('/meetings/{meeting}', [SecretaryMeetingController::class, 'destroy'])->name('meetings.destroy');

    // Office memos & announcements
    Route::get('/memos', [SecretaryMemoController::class, 'index'])->name('memos');
    Route::post('/memos', [SecretaryMemoController::class, 'store'])->name('memos.store');
    Route::post('/memos/{memo}/publish', [SecretaryMemoController::class, 'publish'])->name('memos.publish');
    Route::post('/memos/{memo}/archive', [SecretaryMemoController::class, 'archive'])->name('memos.archive');
    Route::delete('/memos/{memo}', [SecretaryMemoController::class, 'destroy'])->name('memos.destroy');
});
