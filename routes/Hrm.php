<?php

use App\Http\Controllers\Hrm\AnalyticsController;
use App\Http\Controllers\Hrm\ApplicantController as HrmApplicantController;
use App\Http\Controllers\Hrm\EmployeeController;
use App\Http\Controllers\Hrm\AgencyController;
use App\Http\Controllers\Hrm\WorkerController;
use App\Http\Controllers\Hrm\ManpowerRequestController;
use App\Http\Controllers\Hrm\AttendanceController;
use App\Http\Controllers\Hrm\BillingController;
use App\Http\Controllers\Hrm\CompensationController;
use App\Http\Controllers\Hrm\HrmDashboardController;
use App\Http\Controllers\Hrm\InterviewController;
use App\Http\Controllers\Hrm\PayrollController;
use App\Http\Controllers\Hrm\PayrollRatesController;
use App\Http\Controllers\Hrm\PayrollSettingsController;
use App\Http\Controllers\Hrm\PositionController;
use App\Http\Controllers\Hrm\TraineeController;
use App\Http\Controllers\Hrm\AssignedTraineesController;
// HRM_NEW bridge controllers
use App\Http\Controllers\Hrm\Workforce\DepartmentController as NewDepartmentController;
use App\Http\Controllers\Hrm\Workforce\PositionController as NewPositionController;
use App\Http\Controllers\Hrm\Workforce\EmploymentTypeController as NewEmploymentTypeController;
use App\Http\Controllers\Hrm\Workforce\EmployeeDirectoryController as NewEmployeeDirectoryController;
use App\Http\Controllers\Hrm\Workforce\LeaveManagementController as NewLeaveManagementController;
use App\Http\Controllers\Hrm\Workforce\AttendanceController as NewAttendanceController;
use App\Http\Controllers\Hrm\Workforce\MyAttendanceController as NewMyAttendanceController;
use App\Http\Controllers\Hrm\Recruitment\JobPostingController as NewJobPostingController;
use App\Http\Controllers\Hrm\Recruitment\ApplicationController as NewApplicationController;
use App\Http\Controllers\Hrm\Recruitment\InterviewController as NewInterviewController;
use App\Http\Controllers\Hrm\Onboarding\StatusController as NewOnboardingStatusController;
use App\Http\Controllers\Hrm\Onboarding\TemplateController as NewOnboardingTemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Human Resources Management (HRM) Routes
|--------------------------------------------------------------------------
*/
Route::get('/active-positions', [PositionController::class, 'getActivePositions'])->name('active.positions');
Route::prefix('dashboard/hrm')->name('hrm.')->middleware(['auth', 'verified'])->group(function () {
    // Dashboard (page: dashboard)
    Route::get('/', [HrmDashboardController::class, 'index'])
        ->middleware('page.permission:dashboard,view')
        ->name('dashboard');

    // Employees (page: employee)
    Route::get('/employees', [EmployeeController::class, 'index'])
        ->middleware('page.permission:employee,view')
        ->name('employees.index');
    Route::get('/employees/{id}', [EmployeeController::class, 'show'])
        ->middleware('page.permission:employee,view')
        ->name('employees.show');
    Route::patch('/employees/{id}', [EmployeeController::class, 'update'])
        ->middleware('page.permission:employee,edit')
        ->name('employees.update');
    Route::delete('/employees/{id}', [EmployeeController::class, 'toggleStatus'])
        ->middleware('page.permission:employee,edit')
        ->name('employees.toggle-status');
    Route::post('/employees/{id}/promote-to-manager', [EmployeeController::class, 'promoteToManager'])
        ->middleware('page.permission:employee,edit')
        ->name('employees.promote-to-manager');
    Route::post('/employees/{id}/demote-to-staff', [EmployeeController::class, 'demoteToStaff'])
        ->middleware('page.permission:employee,edit')
        ->name('employees.demote-to-staff');
    Route::patch('/employees/{id}/role-position', [EmployeeController::class, 'updateRolePosition'])
        ->middleware('page.permission:employee,edit')
        ->name('employees.update-role-position');

    // Agencies (page: agencies) — manpower agencies Monti Corp works with
    Route::prefix('agencies')->name('agencies.')->group(function () {
        Route::get('/', [AgencyController::class, 'index'])
            ->middleware('page.permission:agencies,view')->name('index');
        Route::get('/create', [AgencyController::class, 'create'])
            ->middleware('page.permission:agencies,edit')->name('create');
        Route::post('/', [AgencyController::class, 'store'])
            ->middleware('page.permission:agencies,edit')->name('store');
        Route::get('/{agency}', [AgencyController::class, 'show'])
            ->middleware('page.permission:agencies,view')->name('show');
        Route::get('/{agency}/edit', [AgencyController::class, 'edit'])
            ->middleware('page.permission:agencies,edit')->name('edit');
        Route::put('/{agency}', [AgencyController::class, 'update'])
            ->middleware('page.permission:agencies,edit')->name('update');
        Route::post('/{agency}/suspend', [AgencyController::class, 'suspend'])
            ->middleware('page.permission:agencies,edit')->name('suspend');
        Route::post('/{agency}/reactivate', [AgencyController::class, 'reactivate'])
            ->middleware('page.permission:agencies,edit')->name('reactivate');
        Route::post('/{agency}/terminate', [AgencyController::class, 'terminate'])
            ->middleware('page.permission:agencies,edit')->name('terminate');
        Route::post('/{agency}/users', [AgencyController::class, 'storeUser'])
            ->middleware('page.permission:agencies,edit')->name('users.store');
        Route::post('/{agency}/users/{user}/reset-password', [AgencyController::class, 'resetUserPassword'])
            ->middleware('page.permission:agencies,edit')->name('users.reset-password');
        Route::post('/{agency}/users/{user}/toggle-status', [AgencyController::class, 'toggleUserStatus'])
            ->middleware('page.permission:agencies,edit')->name('users.toggle-status');
    });

    // Agency workers (page: workers)
    Route::prefix('workers')->name('workers.')->group(function () {
        Route::get('/', [WorkerController::class, 'index'])
            ->middleware('page.permission:workers,view')->name('index');
        Route::get('/create', [WorkerController::class, 'create'])
            ->middleware('page.permission:workers,edit')->name('create');
        Route::post('/', [WorkerController::class, 'store'])
            ->middleware('page.permission:workers,edit')->name('store');
        Route::get('/{worker}', [WorkerController::class, 'show'])
            ->middleware('page.permission:workers,view')->name('show');
        Route::get('/{worker}/edit', [WorkerController::class, 'edit'])
            ->middleware('page.permission:workers,edit')->name('edit');
        Route::put('/{worker}', [WorkerController::class, 'update'])
            ->middleware('page.permission:workers,edit')->name('update');
        Route::post('/{worker}/deactivate', [WorkerController::class, 'deactivate'])
            ->middleware('page.permission:workers,edit')->name('deactivate');
        Route::post('/{worker}/reactivate', [WorkerController::class, 'reactivate'])
            ->middleware('page.permission:workers,edit')->name('reactivate');
        Route::post('/{worker}/blacklist', [WorkerController::class, 'blacklist'])
            ->middleware('page.permission:workers,edit')->name('blacklist');
        Route::post('/{worker}/lift-blacklist', [WorkerController::class, 'liftBlacklist'])
            ->middleware('page.permission:workers,edit')->name('lift-blacklist');
        Route::post('/{worker}/approve', [WorkerController::class, 'approvePending'])
            ->middleware('page.permission:workers,edit')->name('approve');
        Route::post('/{worker}/reject-pending', [WorkerController::class, 'rejectPending'])
            ->middleware('page.permission:workers,edit')->name('reject-pending');
        Route::post('/{worker}/request-changes', [WorkerController::class, 'requestChanges'])
            ->middleware('page.permission:workers,edit')->name('request-changes');
        Route::post('/rate-requests/{rateRequest}/approve', [WorkerController::class, 'approveRate'])
            ->middleware('page.permission:workers,edit')->name('rates.approve');
        Route::post('/rate-requests/{rateRequest}/reject', [WorkerController::class, 'rejectRate'])
            ->middleware('page.permission:workers,edit')->name('rates.reject');
        Route::post('/documents/{document}/verify', [WorkerController::class, 'verifyDocument'])
            ->middleware('page.permission:workers,edit')->name('documents.verify');
    });

    // Manpower requests (page: requests)
    Route::prefix('manpower-requests')->name('manpower-requests.')->group(function () {
        Route::get('/', [ManpowerRequestController::class, 'index'])
            ->middleware('page.permission:requests,view')->name('index');
        Route::get('/create', [ManpowerRequestController::class, 'create'])
            ->middleware('page.permission:requests,edit')->name('create');
        Route::post('/', [ManpowerRequestController::class, 'store'])
            ->middleware('page.permission:requests,edit')->name('store');
        Route::get('/{manpowerRequest}', [ManpowerRequestController::class, 'show'])
            ->middleware('page.permission:requests,view')->name('show');
        Route::get('/{manpowerRequest}/edit', [ManpowerRequestController::class, 'edit'])
            ->middleware('page.permission:requests,edit')->name('edit');
        Route::put('/{manpowerRequest}', [ManpowerRequestController::class, 'update'])
            ->middleware('page.permission:requests,edit')->name('update');
        Route::post('/{manpowerRequest}/submit', [ManpowerRequestController::class, 'submit'])
            ->middleware('page.permission:requests,edit')->name('submit');
        Route::post('/{manpowerRequest}/cancel', [ManpowerRequestController::class, 'cancel'])
            ->middleware('page.permission:requests,edit')->name('cancel');
        Route::post('/{manpowerRequest}/close', [ManpowerRequestController::class, 'close'])
            ->middleware('page.permission:requests,edit')->name('close');
        Route::post('/{manpowerRequest}/assign', [ManpowerRequestController::class, 'assign'])
            ->middleware('page.permission:requests,edit')->name('assign');
        Route::post('/{manpowerRequest}/assignments/{assignment}/release', [ManpowerRequestController::class, 'release'])
            ->middleware('page.permission:requests,edit')->name('release');
        Route::post('/{manpowerRequest}/proposals/{proposal}/accept', [ManpowerRequestController::class, 'acceptProposal'])
            ->middleware('page.permission:requests,edit')->name('proposals.accept');
        Route::post('/{manpowerRequest}/proposals/{proposal}/reject', [ManpowerRequestController::class, 'rejectProposal'])
            ->middleware('page.permission:requests,edit')->name('proposals.reject');
    });

    // Agency attendance (page: agency_attendance)
    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('/', [AttendanceController::class, 'index'])
            ->middleware('page.permission:agency_attendance,view')->name('index');
        Route::post('/', [AttendanceController::class, 'store'])
            ->middleware('page.permission:agency_attendance,edit')->name('store');
        Route::get('/bulk', [AttendanceController::class, 'bulk'])
            ->middleware('page.permission:agency_attendance,edit')->name('bulk');
        Route::post('/bulk', [AttendanceController::class, 'bulkStore'])
            ->middleware('page.permission:agency_attendance,edit')->name('bulk.store');
        Route::get('/approval', [AttendanceController::class, 'approval'])
            ->middleware('page.permission:agency_attendance,view')->name('approval');
        Route::put('/{attendance}', [AttendanceController::class, 'update'])
            ->middleware('page.permission:agency_attendance,edit')->name('update');
        Route::post('/{attendance}/approve', [AttendanceController::class, 'approve'])
            ->middleware('page.permission:agency_attendance,edit')->name('approve');
        Route::post('/approve-all', [AttendanceController::class, 'approveAll'])
            ->middleware('page.permission:agency_attendance,edit')->name('approve-all');
        Route::post('/{attendance}/reject', [AttendanceController::class, 'reject'])
            ->middleware('page.permission:agency_attendance,edit')->name('reject');
        Route::post('/{attendance}/adjust', [AttendanceController::class, 'adjust'])
            ->middleware('page.permission:agency_attendance,edit')->name('adjust');
        Route::post('/{attendance}/reject-dispute', [AttendanceController::class, 'rejectDispute'])
            ->middleware('page.permission:agency_attendance,edit')->name('reject-dispute');
    });

    // Agency billing (page: agency_billing)
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [BillingController::class, 'index'])
            ->middleware('page.permission:agency_billing,view')->name('index');
        Route::get('/create', [BillingController::class, 'create'])
            ->middleware('page.permission:agency_billing,edit')->name('create');
        Route::post('/', [BillingController::class, 'store'])
            ->middleware('page.permission:agency_billing,edit')->name('store');
        Route::get('/{statement}', [BillingController::class, 'show'])
            ->middleware('page.permission:agency_billing,view')->name('show');
        Route::get('/{statement}/edit', [BillingController::class, 'edit'])
            ->middleware('page.permission:agency_billing,edit')->name('edit');
        Route::put('/{statement}', [BillingController::class, 'update'])
            ->middleware('page.permission:agency_billing,edit')->name('update');
        Route::post('/{statement}/issue', [BillingController::class, 'issue'])
            ->middleware('page.permission:agency_billing,edit')->name('issue');
        Route::post('/{statement}/pay', [BillingController::class, 'pay'])
            ->middleware('page.permission:agency_billing,edit')->name('pay');
        Route::post('/{statement}/cancel', [BillingController::class, 'cancel'])
            ->middleware('page.permission:agency_billing,edit')->name('cancel');
        Route::get('/{statement}/export-pdf', [BillingController::class, 'exportPdf'])
            ->middleware('page.permission:agency_billing,view')->name('export-pdf');
        Route::get('/{statement}/export-excel', [BillingController::class, 'exportExcel'])
            ->middleware('page.permission:agency_billing,view')->name('export-excel');
    });

    // Applications (page: application)
    Route::get('/applications', [HrmApplicantController::class, 'index'])
        ->middleware('page.permission:application,view')
        ->name('applications.index');
    Route::post('/applications', [HrmApplicantController::class, 'store'])
        ->middleware('page.permission:application,edit')
        ->name('applications.store');
    Route::post('/applications/{id}/accept', [HrmApplicantController::class, 'accept'])
        ->middleware('page.permission:application,edit')
        ->name('applications.accept');
    Route::post('/applications/{id}/reject', [HrmApplicantController::class, 'reject'])
        ->middleware('page.permission:application,edit')
        ->name('applications.reject');
    Route::get('/rejected', [HrmApplicantController::class, 'rejected'])
        ->middleware('page.permission:application,view')
        ->name('applications.rejected');

    // Interview (page: interview) – HRM now manages all interviews across modules
    Route::get('/interview', [InterviewController::class, 'index'])
        ->middleware('page.permission:interview,view')
        ->name('interview.index');
    Route::post('/interview/{id}/schedule', [InterviewController::class, 'schedule'])
        ->middleware('page.permission:interview,edit')
        ->name('interview.schedule');
    Route::post('/interview/{id}/pass', [InterviewController::class, 'pass'])
        ->middleware('page.permission:interview,edit')
        ->name('interview.pass');
    Route::post('/interview/{id}/fail', [InterviewController::class, 'fail'])
        ->middleware('page.permission:interview,edit')
        ->name('interview.fail');
    Route::post('/interview/{id}/pass-to-other', [InterviewController::class, 'passToOtherModule'])
        ->middleware('page.permission:interview,edit')
        ->name('interview.pass-to-other');

    // Trainee (page: trainee) – HRM now manages all trainees across modules
    Route::get('/trainee', [TraineeController::class, 'index'])
        ->middleware('page.permission:trainee,view')
        ->name('trainee.index');
    Route::get('/trainee/{id}', [TraineeController::class, 'show'])
        ->middleware('page.permission:trainee,view')
        ->name('trainee.show');
    Route::post('/trainee/{id}/evaluate', [TraineeController::class, 'evaluate'])
        ->middleware('page.permission:trainee,edit')
        ->name('trainee.evaluate');
    Route::post('/trainee/{id}/retrain', [TraineeController::class, 'retrain'])
        ->middleware('page.permission:trainee,edit')
        ->name('trainee.retrain');
    Route::post('/trainee/{id}/transfer', [TraineeController::class, 'transfer'])
        ->middleware('page.permission:trainee,edit')
        ->name('trainee.transfer');
    Route::post('/trainee/{id}/grade', [TraineeController::class, 'grade'])
        ->middleware('page.permission:trainee,edit')
        ->name('trainee.grade');
    Route::post('/trainee/{id}/pass', [TraineeController::class, 'pass'])
        ->middleware('page.permission:trainee,edit')
        ->name('trainee.pass');
    Route::post('/trainee/{id}/fail', [TraineeController::class, 'fail'])
        ->middleware('page.permission:trainee,edit')
        ->name('trainee.fail');
    Route::post('/trainee/{id}/hr-review', [TraineeController::class, 'hrReview'])
        ->middleware('page.permission:trainee,edit')
        ->name('trainee.hr-review');

    // My Assigned Trainees (reporting-manager workspace, strictly manager_id == me)
    Route::get('/my-assigned-trainees', [AssignedTraineesController::class, 'index'])
        ->middleware('page.permission:trainee,view')
        ->name('assigned-trainees.index');
    Route::get('/my-assigned-trainees/{id}', [AssignedTraineesController::class, 'show'])
        ->middleware('page.permission:trainee,view')
        ->name('assigned-trainees.show');
    Route::post('/my-assigned-trainees/{id}/weekly', [AssignedTraineesController::class, 'weekly'])
        ->middleware('page.permission:trainee,edit')
        ->name('assigned-trainees.weekly');
    Route::post('/my-assigned-trainees/{id}/final', [AssignedTraineesController::class, 'final'])
        ->middleware('page.permission:trainee,edit')
        ->name('assigned-trainees.final');
    Route::post('/my-assigned-trainees/{id}/clock', [AssignedTraineesController::class, 'clock'])
        ->middleware('page.permission:trainee,edit')
        ->name('assigned-trainees.clock');
    Route::post('/my-assigned-trainees/{id}/observation', [AssignedTraineesController::class, 'observation'])
        ->middleware('page.permission:trainee,edit')
        ->name('assigned-trainees.observation');

    // Bare onboarding URL (e.g. top-bar breadcrumb) → current onboarding page.
    Route::redirect('/onboarding', '/dashboard/hrm/onboarding/status')
        ->middleware('page.permission:onboarding,view')
        ->name('onboarding.redirect');

    // Payroll (page: payroll)
    Route::get('/payroll', [PayrollController::class, 'index'])
        ->middleware('page.permission:payroll,view')
        ->name('payroll');
    Route::get('/payroll/employees.json', [PayrollController::class, 'employeesJson'])
        ->middleware('page.permission:payroll,view')
        ->name('payroll.employees.json');

    // Generate Payroll Form
    Route::get('/payroll/generate', [PayrollController::class, 'create'])
        ->middleware('page.permission:payroll,view')
        ->name('payroll.generate');

    // Generate Payroll Submission (POST)
    Route::post('/payroll/generate', [PayrollController::class, 'generate'])
        ->middleware('page.permission:payroll,edit')
        ->name('payroll.generate.store');

    // Payroll Settings (own page — must sit above {payroll} or "settings" is captured as an id)
    Route::get('/payroll/settings', [PayrollSettingsController::class, 'index'])
        ->middleware('page.permission:payroll,view')
        ->name('payroll.settings');
    Route::put('/payroll/settings', [PayrollSettingsController::class, 'update'])
        ->middleware('page.permission:payroll,edit')
        ->name('payroll.settings.update');

    Route::get('/payroll/{payroll}', [PayrollController::class, 'show'])
        ->middleware('page.permission:payroll,view')
        ->name('payroll.show');

    // Semi-monthly workflow: Draft -> Submitted -> Approved -> Paid (Paid is final)
    Route::post('/payroll/submit', [PayrollController::class, 'submit'])
        ->middleware('page.permission:payroll,edit')
        ->name('payroll.submit');
    Route::post('/payroll/approve-batch', [PayrollController::class, 'approveBatch'])
        ->middleware('page.permission:payroll,edit')
        ->name('payroll.approve.batch');
    Route::post('/payroll/return', [PayrollController::class, 'returnToDraft'])
        ->middleware('page.permission:payroll,edit')
        ->name('payroll.return');
    Route::post('/payroll/mark-paid', [PayrollController::class, 'markPaid'])
        ->middleware('page.permission:payroll,edit')
        ->name('payroll.paid');
    Route::patch('/payroll/{payroll}', [PayrollController::class, 'update'])
        ->middleware('page.permission:payroll,edit')
        ->name('payroll.update');
    Route::post('/payroll/{payroll}/sync-attendance', [PayrollController::class, 'syncAttendance'])
        ->middleware('page.permission:payroll,edit')
        ->name('payroll.sync');
    Route::delete('/payroll/{payroll}', [PayrollController::class, 'destroy'])
        ->middleware('page.permission:payroll,edit')
        ->name('payroll.destroy');

    Route::post('/payroll/{payroll}/approve', [PayrollController::class, 'approve'])
        ->middleware('page.permission:payroll,edit')
        ->name('payroll.approve');

    Route::post('/payroll/{payroll}/reject', [PayrollController::class, 'reject'])
        ->middleware('page.permission:payroll,edit')
        ->name('payroll.reject');

    // Payroll Rates (simple version)
    Route::get('/payroll/rates', [PayrollController::class, 'rates'])
        ->middleware('page.permission:payroll,view')
        ->name('payroll.rates');
    Route::post('/payroll-rates', [PayrollController::class, 'updateRates'])
        ->middleware('page.permission:payroll,edit')
        ->name('payroll.rates.update');

    // Analytics (page: analytics)
    Route::get('/analytics', [AnalyticsController::class, 'index'])
        ->middleware('page.permission:analytics,view')
        ->name('analytics');

    // Compensation & Salary Management (page: compensation).
    // Read-only salary data; every mutation below requires edit.
    Route::get('/compensation', [CompensationController::class, 'index'])
        ->middleware('page.permission:compensation,view')
        ->name('compensation');
    Route::post('/compensation/adjustments', [CompensationController::class, 'store'])
        ->middleware('page.permission:compensation,edit')
        ->name('compensation.adjustments.store');
    Route::put('/compensation/adjustments/{adjustment}', [CompensationController::class, 'update'])
        ->middleware('page.permission:compensation,edit')
        ->name('compensation.adjustments.update');
    Route::post('/compensation/adjustments/{adjustment}/submit', [CompensationController::class, 'submit'])
        ->middleware('page.permission:compensation,edit')
        ->name('compensation.adjustments.submit');
    Route::post('/compensation/adjustments/{adjustment}/approve', [CompensationController::class, 'approve'])
        ->middleware('page.permission:compensation,edit')
        ->name('compensation.adjustments.approve');
    Route::post('/compensation/adjustments/{adjustment}/reject', [CompensationController::class, 'reject'])
        ->middleware('page.permission:compensation,edit')
        ->name('compensation.adjustments.reject');
    Route::post('/compensation/adjustments/{adjustment}/cancel', [CompensationController::class, 'cancel'])
        ->middleware('page.permission:compensation,edit')
        ->name('compensation.adjustments.cancel');
    Route::post('/compensation/apply-due', [CompensationController::class, 'applyDue'])
        ->middleware('page.permission:compensation,edit')
        ->name('compensation.apply-due');
    Route::post('/compensation/components', [CompensationController::class, 'componentStore'])
        ->middleware('page.permission:compensation,edit')
        ->name('compensation.components.store');
    Route::post('/compensation/components/{component}/toggle', [CompensationController::class, 'componentToggle'])
        ->middleware('page.permission:compensation,edit')
        ->name('compensation.components.toggle');

    // Positions (internal JSON + management, guarded like the rest of HRM)
    Route::get('/positions', [PositionController::class, 'index'])
        ->middleware('page.permission:employee,view')->name('positions.index');
    Route::post('/positions', [PositionController::class, 'store'])
        ->middleware('page.permission:employee,edit')
        ->name('positions.store');
    Route::patch('/positions/{id}/toggle-status', [PositionController::class, 'toggleStatus'])
        ->middleware('page.permission:employee,edit')
        ->name('positions.toggle-status');
    Route::delete('/positions/{position}', [PositionController::class, 'destroy'])
        ->middleware('page.permission:employee,edit')
        ->name('positions.destroy');
    Route::patch('/positions/{id}', [PositionController::class, 'update'])
        ->middleware('page.permission:employee,edit')
        ->name('positions.update');

    // ------------------------------------------------------------------
    // NEW: Payroll Rates Configuration (HRM managers / CEO)
    // ------------------------------------------------------------------
    Route::prefix('payroll-rates')->name('payroll-rates.')->group(function () {
        Route::get('/', [PayrollRatesController::class, 'index'])
            ->middleware('page.permission:payroll,view')->name('index');
        Route::post('/set', [PayrollRatesController::class, 'storePayrollSet'])
            ->middleware('page.permission:payroll,edit')->name('store-set');
        Route::put('/set/{payrollSet}', [PayrollRatesController::class, 'updatePayrollSet'])
            ->middleware('page.permission:payroll,edit')->name('update-set');
        Route::post('/set/{payrollSet}/toggle', [PayrollRatesController::class, 'togglePayrollSet'])
            ->middleware('page.permission:payroll,edit')->name('toggle-set');
        Route::post('/contribution', [PayrollRatesController::class, 'storeContributionRate'])
            ->middleware('page.permission:payroll,edit')->name('store-contribution');
        Route::put('/contribution/{rate}', [PayrollRatesController::class, 'updateContributionRate'])
            ->middleware('page.permission:payroll,edit')->name('update-contribution');
        Route::post('/contribution/{rate}/toggle', [PayrollRatesController::class, 'toggleContributionRate'])
            ->middleware('page.permission:payroll,edit')->name('toggle-contribution');
    });

    // ==================================================================
    // HRM_NEW bridge (replaces old HRM UI, keeps old routes above for BC)
    // Workforce / Recruitment / Onboarding expected by HRM_NEW/*.vue
    // Permission mapping reuses canonical keys so IT/AccessGate keep working:
    //   workforce pages -> employee, recruitment -> application/interview,
    //   onboarding -> onboarding
    // ==================================================================
    Route::prefix('workforce')->name('workforce.')->group(function () {
        Route::get('/departments', [NewDepartmentController::class, 'index'])
            ->middleware('page.permission:employee,view')->name('departments.index');
        Route::post('/departments', [NewDepartmentController::class, 'store'])
            ->middleware('page.permission:employee,edit')->name('departments.store');
        Route::put('/departments/{department}', [NewDepartmentController::class, 'update'])
            ->middleware('page.permission:employee,edit')->name('departments.update');
        Route::post('/departments/{department}/archive', [NewDepartmentController::class, 'archive'])
            ->middleware('page.permission:employee,edit')->name('departments.archive');
        Route::post('/departments/{department}/reactivate', [NewDepartmentController::class, 'reactivate'])
            ->middleware('page.permission:employee,edit')->name('departments.reactivate');
        Route::get('/departments/export', [NewDepartmentController::class, 'export'])
            ->middleware('page.permission:employee,view')->name('departments.export');
        Route::get('/departments/stats', [NewDepartmentController::class, 'stats'])
            ->middleware('page.permission:employee,view')->name('departments.stats');
        Route::post('/departments/merge', [NewDepartmentController::class, 'merge'])
            ->middleware('page.permission:employee,edit')->name('departments.merge');
        Route::post('/departments/split', [NewDepartmentController::class, 'split'])
            ->middleware('page.permission:employee,edit')->name('departments.split');
        Route::get('/departments/{department}', [NewDepartmentController::class, 'show'])
            ->middleware('page.permission:employee,view')->name('departments.show');
        Route::delete('/departments/{department}', [NewDepartmentController::class, 'destroy'])
            ->middleware('page.permission:employee,edit')->name('departments.destroy');

        Route::get('/positions', [NewPositionController::class, 'index'])
            ->middleware('page.permission:employee,view')->name('positions.index');
        Route::post('/positions', [NewPositionController::class, 'store'])
            ->middleware('page.permission:employee,edit')->name('positions.store');
        Route::put('/positions/{position}', [NewPositionController::class, 'update'])
            ->middleware('page.permission:employee,edit')->name('positions.update');
        Route::delete('/positions/{position}', [NewPositionController::class, 'destroy'])
            ->middleware('page.permission:employee,edit')->name('positions.destroy');
        Route::post('/positions/{position}/archive', [NewPositionController::class, 'archive'])
            ->middleware('page.permission:employee,edit')->name('positions.archive');
        Route::post('/positions/{position}/reactivate', [NewPositionController::class, 'reactivate'])
            ->middleware('page.permission:employee,edit')->name('positions.reactivate');
        Route::get('/positions/{position}/employees', [NewPositionController::class, 'employees'])
            ->middleware('page.permission:employee,view')->name('positions.employees');
        Route::get('/positions/export', [NewPositionController::class, 'export'])
            ->middleware('page.permission:employee,view')->name('positions.export');
        Route::get('/positions/stats', [NewPositionController::class, 'stats'])
            ->middleware('page.permission:employee,view')->name('positions.stats');
        Route::post('/positions/{position}/duplicate', [NewPositionController::class, 'duplicate'])
            ->middleware('page.permission:employee,edit')->name('positions.duplicate');
        Route::get('/positions/{position}', [NewPositionController::class, 'show'])
            ->middleware('page.permission:employee,view')->name('positions.show');

        Route::get('/employment-types', [NewEmploymentTypeController::class, 'index'])
            ->middleware('page.permission:employee,view')->name('employment-types.index');
        Route::get('/employment-types/get-active', [NewEmploymentTypeController::class, 'getActive'])
            ->middleware('page.permission:employee,view')->name('employment-types.get-active');
        Route::post('/employment-types', [NewEmploymentTypeController::class, 'store'])
            ->middleware('page.permission:employee,edit')->name('employment-types.store');
        Route::patch('/employment-types/{employmentType}', [NewEmploymentTypeController::class, 'update'])
            ->middleware('page.permission:employee,edit')->name('employment-types.update');
        Route::delete('/employment-types/{employmentType}', [NewEmploymentTypeController::class, 'destroy'])
            ->middleware('page.permission:employee,edit')->name('employment-types.destroy');
        Route::post('/employment-types/{employmentType}/restore', [NewEmploymentTypeController::class, 'restore'])
            ->middleware('page.permission:employee,edit')->name('employment-types.restore');

        Route::get('/employees', [NewEmployeeDirectoryController::class, 'index'])
            ->middleware('page.permission:employee,view')->name('employees.index');
        Route::get('/employees/{employee}/profile', [NewEmployeeDirectoryController::class, 'show'])
            ->middleware('page.permission:employee,view')->name('employees.profile');
        Route::patch('/employees/{employee}', [NewEmployeeDirectoryController::class, 'update'])
            ->middleware('page.permission:employee,edit')->name('employees.update');
        Route::get('/employees/export', [NewEmployeeDirectoryController::class, 'export'])
            ->middleware('page.permission:employee,view')->name('employees.export');
        Route::post('/employees/{employee}/resend-credentials', [NewEmployeeDirectoryController::class, 'resendCredentials'])
            ->middleware('page.permission:employee,edit')->name('employees.resend-credentials');

        Route::get('/leave', [NewLeaveManagementController::class, 'index'])
            ->middleware('page.permission:leave,view')->name('leave.index');
        Route::post('/leave', [NewLeaveManagementController::class, 'store'])
            ->middleware('page.permission:leave,edit')->name('leave.store');
        Route::post('/leave/{leave}/approve', [NewLeaveManagementController::class, 'approve'])
            ->middleware('page.permission:leave,edit')->name('leave.approve');
        Route::post('/leave/{leave}/reject', [NewLeaveManagementController::class, 'reject'])
            ->middleware('page.permission:leave,edit')->name('leave.reject');

        Route::get('/attendance', [NewAttendanceController::class, 'index'])
            ->middleware('page.permission:attendance,view')->name('attendance.index');
        Route::get('/attendance/export', [NewAttendanceController::class, 'export'])
            ->middleware('page.permission:attendance,view')->name('attendance.export');
        Route::patch('/attendance/{attendance}', [NewAttendanceController::class, 'update'])
            ->middleware('page.permission:attendance,edit')->name('attendance.update');

        Route::get('/holidays', [\App\Http\Controllers\Hrm\Workforce\HolidayController::class, 'index'])
            ->middleware('page.permission:attendance,view')->name('holidays.index');
        Route::post('/holidays', [\App\Http\Controllers\Hrm\Workforce\HolidayController::class, 'store'])
            ->middleware('page.permission:attendance,edit')->name('holidays.store');
        Route::patch('/holidays/{holiday}', [\App\Http\Controllers\Hrm\Workforce\HolidayController::class, 'update'])
            ->middleware('page.permission:attendance,edit')->name('holidays.update');
        Route::delete('/holidays/{holiday}', [\App\Http\Controllers\Hrm\Workforce\HolidayController::class, 'destroy'])
            ->middleware('page.permission:attendance,edit')->name('holidays.destroy');

        Route::get('/my-attendance', [NewMyAttendanceController::class, 'index'])
            ->name('my-attendance.index');
        Route::post('/my-attendance/clock-in', [NewMyAttendanceController::class, 'clockIn'])
            ->name('my-attendance.clock-in');
        Route::post('/my-attendance/clock-out', [NewMyAttendanceController::class, 'clockOut'])
            ->name('my-attendance.clock-out');
        Route::post('/my-attendance/leave', [NewMyAttendanceController::class, 'requestLeave'])
            ->name('my-attendance.leave.store');

        Route::get('/shifts', [\App\Http\Controllers\Hrm\Workforce\ShiftController::class, 'index'])
            ->middleware('page.permission:attendance,view')->name('shifts.index');
        Route::post('/shifts', [\App\Http\Controllers\Hrm\Workforce\ShiftController::class, 'store'])
            ->middleware('page.permission:attendance,edit')->name('shifts.store');
        Route::get('/shifts/employees', [\App\Http\Controllers\Hrm\Workforce\ShiftController::class, 'employees'])
            ->middleware('page.permission:attendance,view')->name('shifts.employees');
        Route::post('/shifts/reassign/{user}', [\App\Http\Controllers\Hrm\Workforce\ShiftController::class, 'reassign'])
            ->middleware('page.permission:attendance,edit')->name('shifts.reassign');
        Route::post('/shifts/reassign-worker/{worker}', [\App\Http\Controllers\Hrm\Workforce\ShiftController::class, 'reassignWorker'])
            ->middleware('page.permission:attendance,edit')->name('shifts.reassign-worker');
        Route::post('/shifts/bulk-assign', [\App\Http\Controllers\Hrm\Workforce\ShiftController::class, 'bulkAssign'])
            ->middleware('page.permission:attendance,edit')->name('shifts.bulk-assign');
        Route::get('/shifts/{shift}', [\App\Http\Controllers\Hrm\Workforce\ShiftController::class, 'show'])
            ->middleware('page.permission:attendance,view')->name('shifts.show');
        Route::patch('/shifts/{shift}', [\App\Http\Controllers\Hrm\Workforce\ShiftController::class, 'update'])
            ->middleware('page.permission:attendance,edit')->name('shifts.update');
        Route::post('/shifts/{shift}/toggle', [\App\Http\Controllers\Hrm\Workforce\ShiftController::class, 'toggle'])
            ->middleware('page.permission:attendance,edit')->name('shifts.toggle');
        Route::post('/shifts/{shift}/assign', [\App\Http\Controllers\Hrm\Workforce\ShiftController::class, 'assign'])
            ->middleware('page.permission:attendance,edit')->name('shifts.assign');
        Route::delete('/shifts/{shift}/unassign/{user}', [\App\Http\Controllers\Hrm\Workforce\ShiftController::class, 'unassign'])
            ->middleware('page.permission:attendance,edit')->name('shifts.unassign');
        Route::delete('/shifts/{shift}/unassign-worker/{worker}', [\App\Http\Controllers\Hrm\Workforce\ShiftController::class, 'unassignWorker'])
            ->middleware('page.permission:attendance,edit')->name('shifts.unassign-worker');

        Route::get('/training', [\App\Http\Controllers\Hrm\Workforce\TrainingController::class, 'index'])
            ->middleware('page.permission:training,view')->name('training.index');
        Route::post('/training', [\App\Http\Controllers\Hrm\Workforce\TrainingController::class, 'store'])
            ->middleware('page.permission:training,edit')->name('training.store');
        Route::patch('/training/{training}', [\App\Http\Controllers\Hrm\Workforce\TrainingController::class, 'update'])
            ->middleware('page.permission:training,edit')->name('training.update');
        Route::delete('/training/{training}', [\App\Http\Controllers\Hrm\Workforce\TrainingController::class, 'destroy'])
            ->middleware('page.permission:training,edit')->name('training.destroy');
        Route::post('/training/{training}/enroll', [\App\Http\Controllers\Hrm\Workforce\TrainingController::class, 'enroll'])
            ->middleware('page.permission:training,edit')->name('training.enroll');
        Route::post('/training/{training}/enrollments/{enrollment}/status', [\App\Http\Controllers\Hrm\Workforce\TrainingController::class, 'enrollmentStatus'])
            ->middleware('page.permission:training,edit')->name('training.enrollments.status');
    });

    Route::prefix('recruitment')->name('recruitment.')->group(function () {
        Route::get('/job-postings', [NewJobPostingController::class, 'index'])
            ->middleware('page.permission:application,view')->name('job-postings.index');
        Route::post('/job-postings', [NewJobPostingController::class, 'store'])
            ->middleware('page.permission:application,edit')->name('job-postings.store');
        Route::put('/job-postings/{jobPosting}', [NewJobPostingController::class, 'update'])
            ->middleware('page.permission:application,edit')->name('job-postings.update');
        Route::delete('/job-postings/{jobPosting}', [NewJobPostingController::class, 'destroy'])
            ->middleware('page.permission:application,edit')->name('job-postings.destroy');
        Route::post('/job-postings/{jobPosting}/publish', [NewJobPostingController::class, 'publish'])
            ->middleware('page.permission:application,edit')->name('job-postings.publish');
        Route::post('/job-postings/{jobPosting}/close', [NewJobPostingController::class, 'close'])
            ->middleware('page.permission:application,edit')->name('job-postings.close');
        Route::post('/job-postings/{jobPosting}/reopen', [NewJobPostingController::class, 'reopen'])
            ->middleware('page.permission:application,edit')->name('job-postings.reopen');
        Route::post('/job-postings/{jobPosting}/duplicate', [NewJobPostingController::class, 'duplicate'])
            ->middleware('page.permission:application,edit')->name('job-postings.duplicate');
        Route::post('/job-postings/{jobPosting}/archive', [NewJobPostingController::class, 'archive'])
            ->middleware('page.permission:application,edit')->name('job-postings.archive');
        Route::post('/job-postings/{jobPosting}/reactivate', [NewJobPostingController::class, 'reactivate'])
            ->middleware('page.permission:application,edit')->name('job-postings.reactivate');
        Route::get('/job-postings/export', [NewJobPostingController::class, 'export'])
            ->middleware('page.permission:application,view')->name('job-postings.export');
        Route::get('/job-postings/stats', [NewJobPostingController::class, 'stats'])
            ->middleware('page.permission:application,view')->name('job-postings.stats');
        Route::get('/job-postings/get-position-details', [NewJobPostingController::class, 'getPositionDetails'])
            ->middleware('page.permission:application,view')->name('job-postings.get-position-details');
        Route::post('/job-postings/{jobPosting}/unpublish', [NewJobPostingController::class, 'unpublish'])
            ->middleware('page.permission:application,edit')->name('job-postings.unpublish');

        Route::get('/applications', [NewApplicationController::class, 'index'])
            ->middleware('page.permission:application,view')->name('applications.index');
        Route::get('/applications/export', [NewApplicationController::class, 'export'])
            ->middleware('page.permission:application,view')->name('applications.export');

        Route::get('/interviews', [NewInterviewController::class, 'index'])
            ->middleware('page.permission:interview,view')->name('interviews.index');
        Route::get('/interviews/list', [NewInterviewController::class, 'list'])
            ->middleware('page.permission:interview,view')->name('interviews.list');
    });

    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/status', [NewOnboardingStatusController::class, 'index'])
            ->middleware('page.permission:onboarding,view')->name('status.index');
        Route::get('/templates', [NewOnboardingTemplateController::class, 'index'])
            ->middleware('page.permission:onboarding,view')->name('templates.index');
        Route::post('/templates', [NewOnboardingTemplateController::class, 'store'])
            ->middleware('page.permission:onboarding,edit')->name('templates.store');
        Route::get('/templates/{template}', [NewOnboardingTemplateController::class, 'show'])
            ->middleware('page.permission:onboarding,view')->name('templates.show');
        Route::put('/templates/{template}', [NewOnboardingTemplateController::class, 'update'])
            ->middleware('page.permission:onboarding,edit')->name('templates.update');
        Route::post('/templates/{template}/duplicate', [NewOnboardingTemplateController::class, 'duplicate'])
            ->middleware('page.permission:onboarding,edit')->name('templates.duplicate');
        Route::post('/templates/{template}/set-status', [NewOnboardingTemplateController::class, 'setStatus'])
            ->middleware('page.permission:onboarding,edit')->name('templates.set-status');
        Route::delete('/templates/{template}', [NewOnboardingTemplateController::class, 'destroy'])
            ->middleware('page.permission:onboarding,edit')->name('templates.destroy');
        Route::post('/templates/{template}/items', [NewOnboardingTemplateController::class, 'itemStore'])
            ->middleware('page.permission:onboarding,edit')->name('templates.items.store');
        Route::put('/templates/{template}/items/{item}', [NewOnboardingTemplateController::class, 'itemUpdate'])
            ->middleware('page.permission:onboarding,edit')->name('templates.items.update');
        Route::delete('/templates/{template}/items/{item}', [NewOnboardingTemplateController::class, 'itemDestroy'])
            ->middleware('page.permission:onboarding,edit')->name('templates.items.destroy');
        Route::patch('/templates/{template}/reorder', [NewOnboardingTemplateController::class, 'reorder'])
            ->middleware('page.permission:onboarding,edit')->name('templates.reorder');
        Route::post('/templates/{template}/activities', [NewOnboardingTemplateController::class, 'activityStore'])
            ->middleware('page.permission:onboarding,edit')->name('templates.activities.store');
        Route::put('/templates/{template}/activities/{activity}', [NewOnboardingTemplateController::class, 'activityUpdate'])
            ->middleware('page.permission:onboarding,edit')->name('templates.activities.update');
        Route::delete('/templates/{template}/activities/{activity}', [NewOnboardingTemplateController::class, 'activityDestroy'])
            ->middleware('page.permission:onboarding,edit')->name('templates.activities.destroy');
        Route::patch('/templates/{template}/activities-reorder', [NewOnboardingTemplateController::class, 'activityReorder'])
            ->middleware('page.permission:onboarding,edit')->name('templates.activities.reorder');
    });

    // Onboarding instance routes (HRM_NEW OnboardingDetail.vue contract)
    Route::get('/onboarding/status/{onboarding}', [NewOnboardingStatusController::class, 'show'])
        ->middleware('page.permission:onboarding,view')->name('onboarding.show');
    Route::patch('/onboarding/{onboarding}', [NewOnboardingStatusController::class, 'update'])
        ->middleware('page.permission:onboarding,edit')->name('onboarding.update');
    Route::patch('/onboarding/{onboarding}/items/{item}/status', [NewOnboardingStatusController::class, 'itemStatus'])
        ->middleware('page.permission:onboarding,edit')->name('onboarding.items.status');
    Route::post('/onboarding/{onboarding}/items/{item}/review', [NewOnboardingStatusController::class, 'itemReview'])
        ->middleware('page.permission:onboarding,edit')->name('onboarding.items.review');
    Route::post('/onboarding/{onboarding}/notes', [NewOnboardingStatusController::class, 'noteStore'])
        ->middleware('page.permission:onboarding,edit')->name('onboarding.notes.store');
    Route::delete('/onboarding/{onboarding}/notes/{note}', [NewOnboardingStatusController::class, 'noteDestroy'])
        ->middleware('page.permission:onboarding,edit')->name('onboarding.notes.destroy');
    Route::post('/onboarding/{onboarding}/activities', [NewOnboardingStatusController::class, 'activityStore'])
        ->middleware('page.permission:onboarding,edit')->name('onboarding.activities.store');
    Route::patch('/onboarding/{onboarding}/activities/{activity}', [NewOnboardingStatusController::class, 'activityUpdate'])
        ->middleware('page.permission:onboarding,edit')->name('onboarding.activities.update');
    Route::delete('/onboarding/{onboarding}/activities/{activity}', [NewOnboardingStatusController::class, 'activityDestroy'])
        ->middleware('page.permission:onboarding,edit')->name('onboarding.activities.destroy');
    Route::post('/onboarding/{onboarding}/complete', [NewOnboardingStatusController::class, 'complete'])
        ->middleware('page.permission:onboarding,edit')->name('onboarding.complete');
    Route::post('/onboarding/{onboarding}/create-employee', [NewOnboardingStatusController::class, 'createEmployee'])
        ->middleware('page.permission:onboarding,edit')->name('onboarding.create-employee');
});

// ==================================================================
// HRM_NEW raw-URL aliases (Interviews.vue / Applications.vue use hard
// /hrm/... paths via axios instead of route()). Same controllers,
// same permission mapping. Keeps new UI functional without rewrite.
// ==================================================================
Route::prefix('hrm')->middleware(['auth', 'verified'])->group(function () {
    // Bare /hrm/recruitment/applications has no page (only POST store) — send
    // direct navigations to the real HRM applications page instead of a 405.
    Route::get('/recruitment/applications', function () {
        return redirect()->route('hrm.recruitment.applications.index', request()->query());
    })->middleware('page.permission:application,view');
    Route::get('/recruitment/applications/hired', [NewApplicationController::class, 'hired'])
        ->middleware('page.permission:application,view');
    Route::get('/recruitment/applications/{application}/profile', [NewApplicationController::class, 'profile'])
        ->middleware('page.permission:application,view');
    Route::post('/recruitment/applications/{application}/start-screening', [NewApplicationController::class, 'startScreening'])
        ->middleware('page.permission:application,edit');
    Route::post('/recruitment/applications/{application}/screening', [NewApplicationController::class, 'screening'])
        ->middleware('page.permission:application,edit');
    Route::post('/recruitment/applications/{application}/reject', [NewApplicationController::class, 'reject'])
        ->middleware('page.permission:application,edit');
    Route::post('/recruitment/applications/{application}/shortlist', [NewApplicationController::class, 'shortlist'])
        ->middleware('page.permission:application,edit');
    Route::get('/recruitment/applications/stats', [NewApplicationController::class, 'stats'])
        ->middleware('page.permission:application,view');
    Route::post('/recruitment/applications/bulk-update', [NewApplicationController::class, 'bulkUpdate'])
        ->middleware('page.permission:application,edit');
    Route::post('/recruitment/applications', [NewApplicationController::class, 'store'])
        ->middleware('page.permission:application,edit');
    Route::put('/recruitment/applications/{application}', [NewApplicationController::class, 'update'])
        ->middleware('page.permission:application,edit');
    Route::delete('/recruitment/applications/{application}', [NewApplicationController::class, 'destroy'])
        ->middleware('page.permission:application,edit');
    Route::get('/recruitment/applications/{application}/summary', [NewApplicationController::class, 'profileSummary'])
        ->middleware('page.permission:application,view');
    Route::get('/recruitment/job-applications/{jobApplication}/status', [NewApplicationController::class, 'applicationStatus'])
        ->middleware('page.permission:application,view');
    Route::post('/recruitment/interviews', [NewInterviewController::class, 'store'])
        ->middleware('page.permission:interview,edit');
    Route::post('/recruitment/interviews/{interview}/reschedule', [NewInterviewController::class, 'reschedule'])
        ->middleware('page.permission:interview,edit');
    Route::post('/recruitment/interviews/{interview}/reschedule/approve', [NewInterviewController::class, 'approveReschedule'])
        ->middleware('page.permission:interview,edit');
    Route::post('/recruitment/interviews/{interview}/reschedule/deny', [NewInterviewController::class, 'denyReschedule'])
        ->middleware('page.permission:interview,edit');
    Route::post('/recruitment/interviews/{interview}/cancel', [NewInterviewController::class, 'cancel'])
        ->middleware('page.permission:interview,edit');
    Route::post('/recruitment/interviews/{interview}/no-show', [NewInterviewController::class, 'noShow'])
        ->middleware('page.permission:interview,edit');
    Route::post('/recruitment/interviews/{interview}/complete', [NewInterviewController::class, 'complete'])
        ->middleware('page.permission:interview,edit');
    Route::post('/recruitment/interviews/{interview}/advance-to-onboarding', [NewInterviewController::class, 'advanceToOnboarding'])
        ->middleware('page.permission:interview,edit');
    Route::post('/recruitment/interviews/{interview}/select', [NewInterviewController::class, 'select'])
        ->middleware('page.permission:interview,edit');
    Route::get('/recruitment/interviewers', [NewInterviewController::class, 'interviewers'])
        ->middleware('page.permission:interview,view');
    Route::get('/recruitment/interviews/list', [NewInterviewController::class, 'list'])
        ->middleware('page.permission:interview,view');
    Route::get('/recruitment/interviews/upcoming', [NewInterviewController::class, 'upcoming'])
        ->middleware('page.permission:interview,view');
    Route::get('/recruitment/interviews/{interview}', [NewInterviewController::class, 'show'])
        ->middleware('page.permission:interview,view');
    Route::put('/recruitment/interviews/{interview}', [NewInterviewController::class, 'update'])
        ->middleware('page.permission:interview,edit');
    Route::get('/recruitment/interviews', [NewInterviewController::class, 'fetch'])
        ->middleware('page.permission:interview,view');

    // Temp public application form (HRM_NEW Temp_applicationForm.vue)
    Route::get('/temp/application', [HrmApplicantController::class, 'tempForm'])->name('hrm.temp.application.index');
    Route::post('/temp/application', [HrmApplicantController::class, 'store'])->name('hrm.temp.application.submit');
});