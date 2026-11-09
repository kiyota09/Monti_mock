<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Trainee\TraineeAttendanceController;
use App\Http\Controllers\Trainee\TraineePayslipController;
use App\Http\Controllers\Trainee\TraineeTimeKeepingController;
use App\Http\Controllers\Users\AppController;
use App\Http\Controllers\Users\ClockController;
use App\Http\Controllers\Users\LeaveController as UserLeaveController;
use App\Http\Controllers\Users\MessengerController;
use App\Http\Controllers\ThemePreferenceController;
use App\Support\ThemePolicy;
use App\Http\Controllers\Users\TopbarController;
use App\Models\Crm\CrmPost;
use App\Models\Hrm\HrmJobPosting;
use App\Models\Inv\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

Route::get('/system/realtime/version', function () {
    return response()->json([
        'version' => (string) Cache::get('erp:realtime:data-version', 0),
    ])->header('Cache-Control', 'no-store, private');
})->name('system.realtime.version');

Route::post('/theme-preference', [ThemePreferenceController::class, 'update'])
    ->name('theme.preference.update');

Route::get('/system/theme-settings', fn () => response()->json(ThemePolicy::forRequest())
    ->header('Cache-Control', 'no-store, private'))
    ->name('system.theme-settings');

Route::get('/', function () {
    $publicMedia = fn (array $media) => collect($media)->map(function ($item) {
        $path = parse_url((string) ($item['url'] ?? ''), PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/storage/')) {
            $item['url'] = url(ltrim($path, '/'));
        }

        return $item;
    })->values();

    $posts = CrmPost::whereNull('archived_at')->with('creator:id,name')->latest()->get()->map(fn ($post) => [
        'id' => $post->id,
        'caption' => $post->caption,
        'media' => $publicMedia($post->media ?? []),
        'created_at' => $post->created_at?->toIso8601String(),
        'creator' => $post->creator?->name ?? 'Monti Textile',
    ]);

    $products = Product::with('images')->where('status', 'Active')->latest()->get()->map(fn ($product) => [
        'id' => $product->id,
        'name' => $product->name,
        'category' => $product->category,
        'images' => $product->images->map(function ($image) {
            $url = (string) $image->url;
            $path = parse_url($url, PHP_URL_PATH);

            return ['url' => is_string($path) && str_starts_with($path, '/storage/') ? url(ltrim($path, '/')) : $url];
        })->values(),
    ]);

    $careerJobs = HrmJobPosting::with(['position', 'employmentType'])
        ->where('status', 'Published')
        ->whereNull('archived_at')
        ->where(fn ($query) => $query->whereNull('closing_date')->orWhereDate('closing_date', '>=', today()))
        ->whereColumn('filled_vacancies', '<', 'vacancies')
        ->latest('published_at')
        ->take(4)
        ->get()
        ->map(fn ($job) => [
            'id' => $job->id,
            'posting_id' => $job->posting_id,
            'title' => $job->title,
            'work_location' => $job->work_location,
            'work_mode' => $job->work_mode,
            'work_schedule' => $job->work_schedule,
            'salary_visibility' => $job->salary_visibility,
            'salary_min' => $job->salary_min,
            'salary_max' => $job->salary_max,
            'salary_range' => '',
            'closing_date' => $job->closing_date?->format('M d, Y'),
            'closing_date_raw' => $job->closing_date?->toDateString(),
            'published_date' => $job->published_at?->format('M d, Y'),
            'published_date_raw' => $job->published_at?->toIso8601String(),
            'posted' => $job->published_at?->diffForHumans() ?? 'Recently',
            'posted_timestamp' => $job->published_at?->timestamp,
            'created_at' => $job->created_at?->toIso8601String(),
            'position_name' => $job->position?->name ?? $job->title,
            'position_code' => $job->position?->code ?? '',
            'department' => $job->department ?? '',
            'job_description' => $job->position?->description ?? '',
            'responsibilities' => $job->position?->responsibilities,
            'qualifications' => $job->position?->qualifications,
            'required_skills' => $job->position?->required_skills,
            'employment_type' => $job->employmentType?->name ?? 'Full-time',
            'reports_to' => $job->reports_to,
            'management_level' => $job->position?->management_level,
            'vacancies' => (int) $job->vacancies,
            'filled_vacancies' => (int) $job->filled_vacancies,
            'available_vacancies' => max(0, (int) $job->vacancies - (int) $job->filled_vacancies),
            'status' => $job->status,
            'hiring_priority' => $job->hiring_priority,
            'is_urgent' => $job->hiring_priority === 'Urgent',
        ])->values();

    return inertia('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => app()->version(),
        'phpVersion' => PHP_VERSION,
        'companyPosts' => $posts,
        'products' => $products,
        'careerJobs' => $careerJobs,
    ]);
});

/*
|--------------------------------------------------------------------------
| Authenticated User Core Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'geofence'])->group(function () {
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

    // Full-page Messenger — opened from the top-bar "expand" button in a new
    // tab. Renders standalone (no sidebar) and reuses the topbar JSON endpoints
    // above for its data.
    Route::get('/messenger', [MessengerController::class, 'page'])->name('messenger.index');
});

/*
|--------------------------------------------------------------------------
| Unified Employee UI Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'geofence', 'position:staff,manager'])->prefix('dashboard/employee-ui')->group(function () {
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
Route::prefix('dashboard/trainee')->middleware(['auth', 'verified', 'geofence', 'position:trainee'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('trainee.dashboard');
    Route::get('/timekeeping', [TraineeTimeKeepingController::class, 'index'])->name('trainee.timekeeping');
    Route::post('/timekeeping/clock', [TraineeTimeKeepingController::class, 'clockInOut'])->name('trainee.timekeeping.clock');
    Route::get('/attendance', [TraineeAttendanceController::class, 'index'])->name('trainee.attendance');
    Route::get('/payslip', [TraineePayslipController::class, 'index'])->name('trainee.payslip');
    Route::get('/payslip/{payroll}', [TraineePayslipController::class, 'show'])->name('trainee.payslip.show');
});
