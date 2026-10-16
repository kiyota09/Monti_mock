<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmInterview;
use App\Models\Hrm\HrmJobPosting;
use App\Models\Hrm\HrmOnboarding;
use App\Models\Hrm\HrmPosition;
use App\Models\Core\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Traits\HasPagePermissions;

class HrmDashboardController extends Controller
{
    use HasPagePermissions;

    /**
     * Display the unified HRM Dashboard.
     * Accessible by Secretary, Special Officers, and HRM-specific roles.
     * NOTE (overseer model): the President oversees HR through executive
     * reports, not this operational dashboard.
     */
    public function index()
    {
        $user = Auth::user();

        // 1. Hierarchy & Role-Based Access Check
        $hasAccess = in_array(strtoupper($user->role), ['HRM']) ||
                     in_array(strtolower($user->position), ['secretary', 'special_officer', 'special officer']);

        if (! $hasAccess) {
            abort(403, 'Unauthorized access to HRM Module.');
        }

        // 2. Statistics for the HRMDashboard.vue component
        $stats = [
            'total_employees' => User::whereIn('position', ['staff', 'manager', 'special_officer', 'secretary'])->count(),
            'active_trainees' => User::where('position', 'trainee')->count(),
            'pending_applications' => Applicant::whereIn('status', ['pending', 'Submitted'])
                ->where('archived', false)
                ->count(),
            'pending_interviews' => Applicant::where('status', 'Interview')
                ->where('archived', false)
                ->count(),
            'pending_onboarding' => User::where('position', 'trainee')
                ->where('is_active', true)
                ->count(),
            'rejected_count' => Applicant::where('archived', true)->count(),
        ];

        // 3. Department distribution (for chart)
        $departments = ['HRM', 'MAN','CRM', 'LOG'];
        $departmentCounts = [];
        foreach ($departments as $dept) {
            $departmentCounts[$dept] = User::where('role', $dept)
                ->where('position', '!=', 'trainee')
                ->where('is_active', true)
                ->count();
        }

        // 4. Attendance trend (last 6 months placeholder)
        $months = [];
        $attendanceValues = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = Carbon::now()->subMonths($i)->format('M');
            $attendanceValues[] = rand(92, 98); // Placeholder – replace with real data if available
        }

        $attendanceTrend = [
            'months' => $months,
            'values' => $attendanceValues,
        ];

        // 5. Get page permissions for the current user (HRM module)
        $permissions = $this->getPagePermissionsForModule('HRM');

        // 6. HRM_NEW command-center props (new IA) + legacy stats for BC
        // 'pending' (public form) and 'Submitted' (portal/HR entry) are the same stage.
        $pendingScreening = Applicant::whereIn('status', ['pending', 'Submitted'])->where('archived', false)->count();
        $totalEmployees = $stats['total_employees'];
        $activeEmployees = User::where('is_active', true)
            ->whereIn('position', ['staff', 'manager', 'special_officer', 'secretary'])->count();

        // Workforce breakdowns (null-safe shapes for HRMDashboard.vue)
        $deptRows = [];
        foreach (['HRM', 'MAN', 'CRM', 'LOG', 'SCM', 'FIN'] as $dept) {
            $deptRows[] = [
                'name' => $dept,
                'count' => User::where('role', $dept)->where('position', '!=', 'trainee')->count(),
                'pct' => 0,
            ];
        }
        $maxDept = max(1, collect($deptRows)->max('count'));
        foreach ($deptRows as &$r) {
            $r['pct'] = $r['count'] > 0 ? (int) round($r['count'] / $maxDept * 100) : 0;
        }
        unset($r);

        $publishedPostings = HrmJobPosting::where('status', 'Published')->latest()->take(5)->get(['id', 'title', 'department']);
        $inProgress = HrmOnboarding::where('status', 'In Progress')->count();
        $completed = HrmOnboarding::where('status', 'Completed')->count();
        $onboardingRecords = HrmOnboarding::latest()->take(5)->get()->map(fn ($o) => [
            'name' => $o->name, 'status' => $o->status, 'template' => 'Standard',
        ])->values()->all();
        $newHires = User::latest()->take(5)->get()->map(fn ($u) => [
            'name' => $u->name, 'position' => $u->position,
            'department' => $u->role, 'date' => optional($u->created_at)->format('M d, Y'),
        ])->values()->all();

        return Inertia::render('Dashboard/HRM_NEW/HRMDashboard', [
            // Legacy (kept so old widgets/tests don't break)
            'stats' => $stats,
            'departmentCounts' => $departmentCounts,
            'attendanceTrend' => $attendanceTrend,
            'permissions' => $permissions,
            // HRM_NEW contract (all arrays defaulted so .length never crashes)
            'kpis' => [
                ['label' => 'Employees', 'value' => $totalEmployees, 'route' => 'hrm.workforce.employees.index', 'perm' => 'dashboard.hr.workforce'],
                ['label' => 'Open Postings', 'value' => HrmJobPosting::where('status', 'Published')->count(), 'route' => 'hrm.recruitment.job-postings.index', 'perm' => 'dashboard.hr.workforce'],
                ['label' => 'Pending Screening', 'value' => $pendingScreening, 'route' => 'hrm.recruitment.applications.index', 'perm' => 'dashboard.hr.workforce'],
                ['label' => 'Interviews', 'value' => HrmInterview::where('status', 'Scheduled')->count(), 'route' => 'hrm.recruitment.interviews.list', 'perm' => 'dashboard.hr.workforce'],
                ['label' => 'Onboarding', 'value' => $inProgress, 'route' => 'hrm.onboarding.status.index', 'perm' => 'dashboard.hr.workforce'],
                ['label' => 'Departments', 'value' => HrmDepartment::whereNull('archived_at')->count(), 'route' => 'hrm.workforce.departments.index', 'perm' => 'dashboard.hr.workforce'],
            ],
            'workforce' => [
                'total' => $totalEmployees,
                'active' => $activeEmployees,
                'departments' => $deptRows,
                'employmentTypes' => [],
                'newHires' => $newHires,
            ],
            'recruitment' => [
                'pendingScreening' => $pendingScreening,
                'activeOpenings' => HrmJobPosting::where('status', 'Published')->count(),
                'total' => Applicant::count(),
                'pipeline' => [
                    ['label' => 'Applied', 'count' => Applicant::count()],
                    ['label' => 'Screening', 'count' => Applicant::where('status', 'Screening')->count()],
                    ['label' => 'Interview', 'count' => Applicant::where('status', 'Interview')->count()],
                    ['label' => 'Offered', 'count' => Applicant::where('status', 'Offered')->count()],
                    ['label' => 'Hired', 'count' => Applicant::where('status', 'Hired')->count()],
                ],
                'publishedPostings' => $publishedPostings,
                'recentApplications' => Applicant::latest()->take(5)->get()->map(fn ($a) => [
                    'name' => trim(($a->first_name ?? '') . ' ' . ($a->last_name ?? '')),
                    'status' => $a->status,
                ])->values()->all(),
            ],
            'interviews' => [
                'pendingCount' => HrmInterview::where('status', 'Scheduled')->count(),
                'upcoming' => HrmInterview::where('status', 'Scheduled')->latest()->take(5)->get()->map(fn ($i) => [
                    'name' => $i->candidate_name ?? 'Candidate',
                    'position' => $i->position,
                    'details' => trim(($i->date?->format('M d, Y') ?? '') . ' ' . ($i->start_time ?? '')),
                ])->values()->all(),
            ],
            'onboarding' => [
                'inProgress' => $inProgress,
                'completed' => $completed,
                'activeTemplates' => \App\Models\Hrm\HrmOnboardingTemplate::where('status', 'Active')->count(),
                'records' => $onboardingRecords,
            ],
            'quickActions' => [
                'employees' => 'hrm.workforce.employees.index',
                'job_posting' => 'hrm.recruitment.job-postings.index',
                'interview' => 'hrm.recruitment.interviews.list',
                'onboarding' => 'hrm.onboarding.status.index',
            ],
        ]);
    }
}