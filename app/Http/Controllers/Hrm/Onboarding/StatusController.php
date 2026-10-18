<?php

namespace App\Http\Controllers\Hrm\Onboarding;

use App\Http\Controllers\Controller;
use App\Mail\EmployeeWelcomeMail;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\ApplicantStatusHistory;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmJobPosting;
use App\Models\Hrm\HrmOnboarding;
use App\Models\Hrm\HrmOnboardingActivity;
use App\Models\Hrm\HrmOnboardingItem;
use App\Models\Hrm\HrmOnboardingNote;
use App\Models\Hrm\HrmOnboardingTemplate;
use App\Models\Hrm\HrmTraining;
use App\Models\Hrm\HrmTrainingEnrollment;
use App\Models\Hrm\SalaryHistory;
use App\Models\Core\User;
use App\Support\OnboardingSnapshot;
use App\Support\TerminatedAccess;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class StatusController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        $viewer = $request->user();
        $fullAccess = self::isFullOnboardingViewer($viewer);

        $q = HrmOnboarding::query();
        if (! $fullAccess) {
            // Reporting managers only see onboardings assigned to them.
            $q->where('assigned_manager', $viewer?->name);
        }
        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        if ($st = $request->get('status')) {
            $q->where('status', $st);
        }
        if ($d = $request->get('department')) {
            $q->where('department', $d);
        }

        $paginated = $q->latest()->paginate(15)->withQueryString();

        $onboardings = collect($paginated->items())->filter(fn ($o) => $o !== null)->map(function ($o) {
            $o->initials = collect(explode(' ', (string) ($o->name ?? '')))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('');
            $o->progress ??= 0;
            return $o;
        })->values()->all();

        // Stats respect the same visibility scope so counts never leak others.
        $statsBase = $fullAccess ? HrmOnboarding::query() : HrmOnboarding::where('assigned_manager', $viewer?->name);
        return Inertia::render('Dashboard/HRM_NEW/Onboarding', [
            'onboardings' => $onboardings,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'stats' => [
                'all' => (clone $statsBase)->count(),
                'In Progress' => (clone $statsBase)->where('status', 'In Progress')->count(),
                'Pending Requirements' => (clone $statsBase)->where('status', 'Pending Requirements')->count(),
                'Ready to Hire' => (clone $statsBase)->where('status', 'Ready to Hire')->count(),
                'Completed' => (clone $statsBase)->where('status', 'Completed')->count(),
            ],
            'filters' => $request->only(['search', 'status', 'department']),
            'departments' => HrmDepartment::select('id', 'name')->get(),
            'templateCount' => HrmOnboardingTemplate::count(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function show(Request $request, HrmOnboarding $onboarding)
    {
        self::authorizeOnboardingAccess($request->user(), $onboarding);
        $onboarding->load(['items.templateItem', 'activities', 'notes', 'template', 'applicant', 'jobPosting.employmentType']);
        // Readiness + progress scope: Applicant Requirements only. HR
        // Processing / Company Preparation never block completion.
        $applicantItems = $onboarding->items->filter(fn ($i) =>
            ! $i->templateItem || ($i->templateItem->category ?? null) === 'Applicant Requirements');
        $required = $applicantItems->where('status', '!=', 'Completed')->count();

        // The person behind this onboarding: linked applicant first,
        // matched by email as fallback (walk-ins / manual records).
        $person = $onboarding->applicant;
        if (! $person && $onboarding->email) {
            $person = Applicant::where('email', $onboarding->email)->first();
        }

        // Backfill empty employment columns from the applicant / job posting
        // so Employment Details never renders blank when HR skipped them.
        // The record often has no job_posting_id, so resolve the posting via
        // the applicant's latest job application, then by position title.
        $posting = $onboarding->jobPosting;
        if (! $posting && $person) {
            $latestJa = $person->jobApplications()->latest()->first();
            $posting = $latestJa?->jobPosting;
            if (! $posting) {
                $title = $onboarding->position ?: $person->position_applied;
                if ($title) {
                    $posting = HrmJobPosting::with('employmentType')
                        ->where('title', $title)->whereNull('archived_at')
                        ->latest()->first();
                }
            }
        }
        if ($person) {
            if (! $onboarding->position) {
                $onboarding->position = $person->position_applied ?: $posting?->title;
            }
            if (! $onboarding->department) {
                $onboarding->department = $posting?->department;
            }
            if (! $onboarding->employment_type) {
                $onboarding->employment_type = $posting?->employmentType?->name;
            }
            if (! $onboarding->work_location) {
                $onboarding->work_location = $posting?->work_location;
            }
            if (! $onboarding->work_mode) {
                $onboarding->work_mode = $posting?->work_mode;
            }
        }
        // The page reads `onboarding.salary` while the column is salary_range.
        // Fall back to the posting range, then the applicant's expectation.
        if (! $onboarding->salary) {
            $salary = $onboarding->salary_range;
            if (! $salary && isset($posting) && ($posting->salary_min || $posting->salary_max)) {
                $parts = [];
                if ($posting->salary_min) {
                    $parts[] = '₱' . number_format((float) $posting->salary_min);
                }
                if ($posting->salary_max) {
                    $parts[] = '₱' . number_format((float) $posting->salary_max);
                }
                $salary = implode(' - ', $parts) ?: null;
            }
            if (! $salary && isset($person) && $person && $person->expected_salary) {
                $salary = '₱' . number_format((float) $person->expected_salary);
            }
            $onboarding->setAttribute('salary', $salary);
        }

        $latestJa = $person?->jobApplications()->latest()->first();
        // Applicant-side direct posting link (applicants.job_posting_id).
        $directPosting = $person?->jobPosting;
        $jaPosting = $latestJa?->jobPosting ?? $posting ?? $directPosting;

        // Heal old/manual onboarding rows missing their links so the
        // conversion modal (and future loads) resolve instantly.
        // NOTE: use the query builder (not $onboarding->update()) because
        // the model carries in-memory backfilled attributes above (`salary`
        // is not a real column) that must never be persisted.
        if ($person && ! $onboarding->applicant_id) {
            HrmOnboarding::where('id', $onboarding->id)->update(['applicant_id' => $person->id]);
            $onboarding->applicant_id = $person->id;
        }
        if ($jaPosting && ! $onboarding->job_posting_id) {
            HrmOnboarding::where('id', $onboarding->id)->update(['job_posting_id' => $jaPosting->id]);
            $onboarding->job_posting_id = $jaPosting->id;
        }

        // Position-chain reporting line: onboarding position → hrm_positions
        // → reports_to parent → active user(s) holding that parent position.
        // This is what auto-fills Reporting Manager in the conversion modal.
        $chainPosition = null;
        if ($jaPosting?->position_id) {
            $chainPosition = \App\Models\Hrm\HrmPosition::with('reportsTo')->find($jaPosting->position_id);
        }
        if (! $chainPosition) {
            $chainTitle = $onboarding->position ?: $person?->position_applied ?: $jaPosting?->title;
            if ($chainTitle) {
                $chainPosition = \App\Models\Hrm\HrmPosition::with('reportsTo')->where('name', $chainTitle)->first();
            }
        }
        $chainNames = [];
        $cursor = $chainPosition;
        for ($i = 0; $i < 6 && $cursor; $i++) {
            $chainNames[] = $cursor->name;
            $cursor = $cursor->relationLoaded('reportsTo') ? $cursor->reportsTo : $cursor->reportsTo()->first();
        }
        $chainManager = null;
        $chainParent = $chainPosition?->reportsTo;
        if ($chainParent) {
            $chainManager = User::where('hrm_position_id', $chainParent->id)
                ->where('is_active', true)->orderBy('id')->first();
        }

        // Conversion payload for the CREATE EMPLOYEE modal (all auto-filled
        // from the application / posting / onboarding — HR only confirms).
        $fullName = $person
            ? trim(implode(' ', array_filter([$person->first_name, $person->middle_name, $person->last_name, $person->suffix])))
            : (string) ($onboarding->name ?? '');
        $suggestedCompanyEmail = $onboarding->email
            ? strtolower(preg_replace('/[^a-z0-9]+/i', '.', explode('@', (string) $onboarding->email)[0]) . '@monti.com')
            : null;
        $suggestedSalary = null;
        if ($jaPosting && ($jaPosting->salary_min || $jaPosting->salary_max)) {
            $suggestedSalary = $jaPosting->salary_max ?: $jaPosting->salary_min;
        } elseif ($person?->expected_salary) {
            $suggestedSalary = $person->expected_salary;
        }

        // Recommended deployment start for the CREATE EMPLOYEE modal:
        // applicant's availability date first, else today + notice-period
        // days, then the onboarding's own dates, then today. The modal field
        // stays editable — this is only the prefilled suggestion.
        $recommendedStart = null;
        $recommendedHint = null;
        if ($latestJa?->availability_date) {
            $availableOn = \Carbon\Carbon::parse($latestJa->availability_date);
            $recommendedStart = $availableOn->toDateString();
            $recommendedHint = 'Recommended from availability date (' . $availableOn->format('M d, Y') . ') — editable';
        } else {
            $noticeRaw = $latestJa?->notice_period ?? $person?->notice_period;
            if ($noticeRaw) {
                $noticeDays = preg_match('/(\d+)/', (string) $noticeRaw, $m) ? (int) $m[1] : 0;
                $recommendedStart = \Carbon\Carbon::today()->addDays($noticeDays)->toDateString();
                $noticeLabel = str_replace('_', ' ', (string) $noticeRaw);
                $recommendedHint = "Recommended from notice period ({$noticeLabel}) — editable";
            }
        }
        $recommendedStart ??= $onboarding->start_date?->toDateString()
            ?? $onboarding->expected_start_date?->toDateString()
            ?? now()->toDateString();

        $conversion = [
            'applicant' => $person ? [
                'id' => $person->id,
                'code' => 'APP-2026-' . str_pad((string) $person->id, 5, '0', STR_PAD_LEFT),
                'name' => $fullName ?: $onboarding->name,
                'position_applied' => $person->position_applied,
                'status' => $person->status,
                // Real HRM applications page (pre-filtered to this applicant).
                // NOTE: /hrm/recruitment/applications/{id}/profile is a JSON
                // alias for axios, not a browsable page — never link there.
                'profile_url' => route('hrm.recruitment.applications.index', ['search' => $person->email]),
            ] : null,
            'job_posting' => $jaPosting ? [
                'id' => $jaPosting->id,
                'title' => $jaPosting->title,
                'department' => $jaPosting->department,
                'work_location' => $jaPosting->work_location,
                'work_mode' => $jaPosting->work_mode,
                'work_schedule' => $jaPosting->work_schedule,
                'employment_type' => $jaPosting->employmentType?->name,
                'salary_min' => $jaPosting->salary_min,
                'salary_max' => $jaPosting->salary_max,
            ] : [
                // Synthetic fallback so the display never renders null —
                // built from the onboarding record itself.
                'id' => null,
                'title' => $onboarding->position ?: $person?->position_applied,
                'department' => $onboarding->department,
                'work_location' => $onboarding->work_location,
                'work_mode' => $onboarding->work_mode,
                'work_schedule' => $onboarding->work_schedule,
                'employment_type' => $onboarding->employment_type,
                'salary_min' => null,
                'salary_max' => null,
                'synthetic' => true,
            ],
            'application' => $latestJa ? [
                'id' => $latestJa->id,
                'code' => 'APP-2026-' . str_pad((string) $latestJa->id, 5, '0', STR_PAD_LEFT),
                'status' => $latestJa->status,
            ] : ($person ? [
                'id' => null,
                'code' => 'APP-2026-' . str_pad((string) $person->id, 5, '0', STR_PAD_LEFT),
                'status' => $person->status,
            ] : [
                'id' => null,
                'code' => 'APP-2026-' . str_pad((string) $onboarding->id, 5, '0', STR_PAD_LEFT),
                'status' => $onboarding->status,
            ]),
            'reporting' => [
                'position' => $chainPosition?->name,
                'reports_to_position' => $chainParent?->name,
                'chain' => $chainNames,
                'manager_id' => $chainManager?->id,
                'manager_name' => $chainManager?->name,
            ],
            'defaults' => [
                // A linked real posting is the source of truth for the hired
                // position — the onboarding row's own position string is often
                // stale (copied from the interview at handoff), so it is only
                // a fallback when no real posting is linked.
                'position' => ($jaPosting && ($jaPosting->id ?? null) && $jaPosting->title)
                    ? $jaPosting->title
                    : ($onboarding->position ?: $person?->position_applied ?: $jaPosting?->title),
                'department' => $onboarding->department ?: $jaPosting?->department,
                'employment_type' => $onboarding->employment_type ?: $jaPosting?->employmentType?->name ?: 'Regular',
                'start_date' => $recommendedStart,
                'start_date_hint' => $recommendedHint,
                'reporting_manager' => $chainManager?->name ?? $onboarding->assigned_manager,
                'reporting_manager_id' => $chainManager?->id,
                'work_location' => $onboarding->work_location ?: $jaPosting?->work_location ?: 'Main Manufacturing Plant',
                'work_mode' => $onboarding->work_mode ?: $jaPosting?->work_mode ?: 'Onsite',
                'work_schedule' => $onboarding->work_schedule ?: $jaPosting?->work_schedule ?: 'Monday – Saturday',
                'basic_salary' => $suggestedSalary,
                'company_email' => $suggestedCompanyEmail,
                'emergency_name' => $person?->emergency_name,
                'emergency_relationship' => $person?->emergency_relationship,
                'emergency_phone' => $person?->emergency_phone,
            ],
            'trainings' => $onboarding->activities->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title,
                'completed' => in_array($a->status, ['Completed'], true),
                'status' => $a->status,
            ])->values()->all(),
            'documents' => $onboarding->items->map(fn ($i) => [
                'id' => $i->id, 'name' => $i->name,
                'completed' => in_array($i->status, ['Completed', 'Verified'], true),
                'status' => $i->status,
            ])->values()->all(),
        ];

        // Reporting-manager picker: supervisors/managers of the department
        // the applicant applied for (onboarding department, else the job
        // posting's). Falls back to all seat-holders when the department is
        // unknown or has no managers, so HR is never stuck.
        $appliedDepartment = $onboarding->department ?: $jaPosting?->department;
        $seatHolders = User::with(['hrmDepartment:id,name', 'hrmOrgPosition:id,management_level'])->where('is_active', true)
            ->where(fn ($q) => $q->where('position', 'manager')
                ->orWhere('is_manufacturing_supervisor', true)
                ->orWhereHas('hrmOrgPosition', fn ($w) => $w->whereIn('management_level', ['Managerial', 'Supervisory', 'Executive'])))
            ->orderBy('name')->take(200)
            ->get(['id', 'name', 'position', 'department', 'role', 'hrm_department_id', 'hrm_position_id', 'is_manufacturing_supervisor', 'supervisor_department']);
        $norm = fn ($v) => strtolower(trim((string) $v));
        $target = $norm($appliedDepartment);
        $managerChoices = $seatHolders;
        if ($target !== '') {
            $scoped = $seatHolders->filter(fn ($u) => $norm($u->hrmDepartment?->name) === $target
                || $norm($u->department) === $target
                || $norm($u->supervisor_department) === $target)->values();
            if ($scoped->isNotEmpty()) {
                $managerChoices = $scoped;
            }
        }
        // Show the resolved department name + seat (Manager/Supervisor) in
        // the picker label.
        $managerChoices->each(function ($u) {
            $u->department = $u->hrmDepartment?->name ?: $u->department;
            $level = $u->hrmOrgPosition?->management_level;
            $isManager = $u->position === 'manager' || in_array($level, ['Managerial', 'Executive'], true);
            $isSupervisor = (bool) ($u->is_manufacturing_supervisor || $u->supervisor_department || $level === 'Supervisory');
            $u->seat = $isManager ? 'Manager' : ($isSupervisor ? 'Supervisor' : ucfirst((string) $u->position));
            $u->unsetRelation('hrmDepartment');
            $u->unsetRelation('hrmOrgPosition');
        });

        return Inertia::render('Dashboard/HRM_NEW/OnboardingDetail', [
            'onboarding' => $onboarding,
            'templateName' => $onboarding->template?->name,
            'sections' => $this->sections($onboarding),
            'activities' => $onboarding->activities,
            'notes' => $onboarding->notes,
            'profile' => $person ? $this->profilePayload($person, $onboarding) : null,
            'canComplete' => $required === 0 && $onboarding->status !== 'Completed',
            'missing' => [],
            'readiness' => [
                'required_items_done' => $applicantItems->where('status', 'Completed')->count(),
                'required_items_total' => $applicantItems->count(),
                'required_activities_done' => $onboarding->activities->where('status', 'Completed')->count(),
                'required_activities_total' => $onboarding->activities->count(),
                'ready' => $required === 0,
                'completed' => $onboarding->status === 'Completed',
            ],
            'progress' => (int) $onboarding->progress,
            // Same email may apply for a different job (new onboarding row,
            // same address). Only hide Create Employee when THIS job was
            // already hired — i.e. a user with this personal email whose org
            // position/department matches this onboarding. A hire for a
            // different position keeps the button visible.
            'alreadyHired' => self::alreadyHiredForOnboarding($onboarding, $person),
            'templates' => HrmOnboardingTemplate::select('id', 'name', 'code')->get(),
            'conversion' => $conversion,
            'managers' => $managerChoices,
            'employmentTypes' => \App\Models\Hrm\HrmEmploymentType::where('is_active', true)
                ->orderBy('name')->get(['id', 'name']),
            'departments' => HrmDepartment::whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'category']),
            'roles' => ['HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'PRO', 'PROJ', 'IT', 'LOG'],
            'shifts' => \App\Models\Hrm\HrmShift::where('is_active', true)->orderBy('code')
                ->get(['id', 'code', 'name', 'shift_type', 'start_time', 'end_time']),
        ]);
    }

    public function update(Request $request, HrmOnboarding $onboarding)
    {
        self::authorizeOnboardingAccess($request->user(), $onboarding);
        $onboarding->update($request->validate([
            'start_date' => 'nullable|date',
            'expected_start_date' => 'nullable|date',
            'expected_completion_date' => 'nullable|date',
            'duration_days' => 'nullable|integer|min:1|max:3650',
            'assigned_manager' => 'nullable|string',
            'work_location' => 'nullable|string',
            'work_schedule' => 'nullable|string',
            'template_id' => 'nullable|exists:hrm_onboarding_templates,id',
        ]));

        // Choosing a template snapshots its items/activities into this
        // onboarding (the modal promises this). Never duplicates.
        $message = 'Onboarding updated.';
        if ($request->filled('template_id') && ($template = HrmOnboardingTemplate::find($onboarding->template_id))) {
            $snap = OnboardingSnapshot::apply($onboarding->fresh()->loadMissing('template'), $template);
            if (! $snap['skipped']) {
                $message = "Template applied: {$snap['items']} items and {$snap['activities']} activities added.";
            }
        }

        return back()->with('success', $message);
    }

    public function itemStatus(Request $request, HrmOnboarding $onboarding, HrmOnboardingItem $item)
    {
        self::authorizeOnboardingAccess($request->user(), $onboarding);
        $item->update(['status' => $request->validate(['status' => 'required|string'])['status']]);
        $this->recalc($onboarding);

        return back()->with('success', 'Item updated.');
    }

    public function itemReview(Request $request, HrmOnboarding $onboarding, HrmOnboardingItem $item)
    {
        self::authorizeOnboardingAccess($request->user(), $onboarding);
        $data = $request->validate(['decision' => 'required|in:approve,reject', 'reason' => 'nullable|string']);
        $item->update([
            'status' => $data['decision'] === 'approve' ? 'Completed' : 'Blocked',
            'rejection_reason' => $data['reason'] ?? null,
        ]);
        $this->recalc($onboarding);

        // No success flash: the HRM detail page stays silent on approve/reject
        // (status badge updates in place). Errors still surface normally.
        return back();
    }

    public function noteStore(Request $request, HrmOnboarding $onboarding)
    {
        HrmOnboardingNote::create([
            'onboarding_id' => $onboarding->id, 'user_id' => $request->user()->id,
            'content' => $request->validate(['content' => 'required|string'])['content'],
            'is_hr_private' => (bool) $request->get('is_hr_private'),
        ]);

        return back()->with('success', 'Note added.');
    }

    public function noteDestroy(HrmOnboarding $onboarding, HrmOnboardingNote $note)
    {
        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    public function activityStore(Request $request, HrmOnboarding $onboarding)
    {
        HrmOnboardingActivity::create($request->validate([
            'title' => 'required|string', 'type' => 'nullable|string', 'description' => 'nullable|string',
            'schedule_date' => 'nullable|date', 'start_time' => 'nullable|string', 'end_time' => 'nullable|string',
            'method' => 'nullable|string', 'location' => 'nullable|string', 'meeting_link' => 'nullable|string',
            'facilitator' => 'nullable|string', 'organizer' => 'nullable|string',
            'is_required' => 'nullable|boolean', 'applicant_visible' => 'nullable|boolean',
        ]) + ['onboarding_id' => $onboarding->id]);

        return back()->with('success', 'Activity added.');
    }

    public function activityUpdate(Request $request, HrmOnboarding $onboarding, HrmOnboardingActivity $activity)
    {
        $activity->update($request->only(['status', 'attendance_status', 'title', 'description', 'schedule_date', 'start_time', 'end_time', 'method', 'location']));
        $this->recalc($onboarding);

        return back()->with('success', 'Activity updated.');
    }

    public function activityDestroy(HrmOnboarding $onboarding, HrmOnboardingActivity $activity)
    {
        $activity->delete();

        return back()->with('success', 'Activity deleted.');
    }

    public function complete(HrmOnboarding $onboarding)
    {
        $onboarding->update(['status' => 'Completed', 'progress' => 100]);

        return back()->with('success', 'Onboarding completed.');
    }

    public function createEmployee(Request $request, HrmOnboarding $onboarding)
    {
        $data = $request->validate([
            'position' => 'required|string|max:255',
            'position_rank' => 'nullable|string|max:60',
            'department' => 'required|string|max:255',
            'employment_type' => 'nullable|string|max:255',
            'employment_type_id' => 'nullable|exists:hrm_employment_types,id',
            'role' => 'nullable|string|in:HRM,SCM,FIN,MAN,INV,ORD,WAR,CRM,ECO,PRO,PROJ,IT,LOG',
            'start_date' => 'required|date',
            'reporting_manager' => 'nullable|string|max:255',
            'reporting_manager_id' => 'nullable|exists:users,id',
            'employee_status' => 'nullable|string|max:60',
            'work_location' => 'nullable|string|max:255',
            'work_mode' => 'nullable|string|max:60',
            'shift' => 'nullable|string|max:255',
            'work_schedule' => 'nullable|string|max:255',
            'work_hours' => 'nullable|string|max:255',
            'salary_type' => 'nullable|string|max:60',
            'basic_salary' => 'nullable|numeric|min:0',
            'pay_frequency' => 'nullable|string|max:60',
            'payroll_group' => 'nullable|string|max:255',
            'company_email' => 'nullable|email|max:255',
            'create_account' => 'nullable|boolean',
            'training_duration_weeks' => 'nullable|integer|min:1|max:52',
            'training_end_date' => 'nullable|date',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'emergency_contact_number' => 'nullable|string|max:40',
        ]);

        // Training schedule: duration (weeks) + end calendar, anchored on the
        // deployment start date. End wins when both are given; otherwise it is
        // derived as start + duration (default 8 weeks).
        $deploymentStart = $data['start_date'] ?? now()->toDateString();
        $durationWeeks = (int) ($data['training_duration_weeks'] ?? 8);
        $durationWeeks = max(1, min(52, $durationWeeks ?: 8));
        try {
            $trainingEnd = ! empty($data['training_end_date'])
                ? \Carbon\Carbon::parse($data['training_end_date'])->toDateString()
                : \Carbon\Carbon::parse($deploymentStart)->addWeeks($durationWeeks)->toDateString();
        } catch (\Throwable $e) {
            $trainingEnd = \Carbon\Carbon::parse($deploymentStart)->addWeeks($durationWeeks)->toDateString();
        }

        $departmentName = $data['department'] ?: ($onboarding->department ?? 'HRM');
        // Explicit role from the form wins; mapping below is only a fallback.
        $role = $data['role'] ?? strtoupper($departmentName);
        $validRoles = ['HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'PRO', 'PROJ', 'IT', 'LOG'];
        $deptModel = HrmDepartment::where('name', $departmentName)->first();
        if (! in_array($role, $validRoles, true)) {
            $role = ($deptModel && in_array(strtoupper((string) $deptModel->category), $validRoles, true))
                ? strtoupper((string) $deptModel->category) : 'HRM';
        }
        $deptId = $deptModel?->id ?? HrmDepartment::where('name', $onboarding->department)->value('id');
        $positionModel = \App\Models\Hrm\HrmPosition::where('name', $data['position'])->first();

        // System rank for users.position (dropdown in CREATE EMPLOYEE modal).
        // Job title ($data['position'], e.g. "HR Manager") stays as the org
        // title (hrm_position_id); this rank drives access gates.
        $rawRank = strtolower(trim((string) ($data['position_rank'] ?? 'staff')));
        $rankAliases = [
            'special_office' => 'special_officer',
            'special-officer' => 'special_officer',
            'special officer' => 'special_officer',
            'vice-president' => 'vice_president',
            'vice president' => 'vice_president',
            'vise-president' => 'vice_president',
            'vise president' => 'vice_president',
            'vicepresident' => 'vice_president',
            'visepresident' => 'vice_president',
            'general_manager' => 'special_officer',
            'general manager' => 'special_officer',
        ];
        $rawRank = $rankAliases[$rawRank] ?? $rawRank;
        $allowedRanks = ['trainee', 'staff', 'supervisor', 'manager', 'secretary', 'special_officer', 'vice_president'];
        $systemPosition = in_array($rawRank, $allowedRanks, true) ? $rawRank : 'staff';

        // Prefer the position-chain manager: resolve the name from the linked
        // user id so a renamed manager account can't desync the record.
        $managerName = $data['reporting_manager'] ?? null;
        if (! $managerName && ! empty($data['reporting_manager_id'])) {
            $managerName = User::where('id', $data['reporting_manager_id'])->value('name');
        }
        // ERP-grade FK: link the employee row directly to the manager row.
        $managerId = ! empty($data['reporting_manager_id']) ? (int) $data['reporting_manager_id'] : null;
        if (! $managerId && $managerName) {
            $managerId = User::where('name', $managerName)->where('is_active', true)->value('id');
        }
        $employmentTypeId = $data['employment_type_id'] ?? null;
        if (! $employmentTypeId && ! empty($data['employment_type'])) {
            $employmentTypeId = \App\Models\Hrm\HrmEmploymentType::where('name', $data['employment_type'])->value('id');
        }

        // Split the onboarding / applicant name into master-record parts.
        $person = $onboarding->applicant_id ? Applicant::find($onboarding->applicant_id) : null;
        if (! $person && $onboarding->email) {
            $person = Applicant::where('email', $onboarding->email)->first();
        }
        $first = $person?->first_name; $middle = $person?->middle_name; $last = $person?->last_name;
        if (! $first || ! $last) {
            $parts = preg_split('/\s+/', trim((string) ($onboarding->name ?? '')), -1, PREG_SPLIT_NO_EMPTY);
            $first ??= array_shift($parts) ?: ($onboarding->name ?? 'New');
            $last ??= $parts ? implode(' ', $parts) : $first;
        }
        $schedule = trim(implode(' • ', array_filter([$data['work_schedule'] ?? null, $data['work_hours'] ?? null])));

        // Final login email: a provided company email wins when free,
        // otherwise auto-generate firstname.lastname@monti.com.
        $finalEmail = $data['company_email'] ?: self::generateCompanyEmail($first, $last);
        if (! $finalEmail) {
            return back()->withErrors(['company_email' => 'No email available for the employee account.']);
        }
        if ($data['company_email'] && $data['company_email'] !== $onboarding->email
            && User::where('email', $data['company_email'])->exists()) {
            return back()->withErrors(['company_email' => 'That company email is already taken.']);
        }

        // Temporary password for brand-new accounts (emailed to the employee).
        $tempPassword = 'Monti-' . Str::upper(Str::random(6));

        $user = User::firstOrCreate(['email' => $finalEmail], [
            'name' => trim("{$first} {$last}"),
            'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last,
            'password' => Hash::make($tempPassword),
            'role' => $role, 'position' => $systemPosition, 'is_active' => true,
            'employee_id' => 'EMP-' . str_pad((string) (User::max('id') + 1), 4, '0', STR_PAD_LEFT),
            'department' => $departmentName,
            'hrm_department_id' => $deptId,
            'hrm_position_id' => $positionModel?->id,
            'hrm_employment_type_id' => $employmentTypeId,
            'join_date' => $data['start_date'],
            'training_start_date' => $deploymentStart,
            'training_end_date' => $trainingEnd,
            'training_duration_weeks' => $durationWeeks,
            'personal_email' => $onboarding->email !== $finalEmail ? $onboarding->email : $person?->email,
            'mobile_number' => $person?->phone_number ?? $person?->contact_number,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? $person?->emergency_name,
            'emergency_contact_number' => $data['emergency_contact_number'] ?? $person?->emergency_phone,
            'current_address' => $person ? trim(implode(', ', array_filter([
                $person->street_address, $person->barangay, $person->city, $person->state_province,
            ]))) ?: null : null,
            'company' => 'MontiTextile',
            'work_location' => $data['work_location'] ?? $onboarding->work_location,
            'work_schedule' => $schedule ?: $onboarding->work_schedule,
            'shift' => $data['shift'] ?? $onboarding->shift ?? null,
            'basic_salary' => $data['basic_salary'] ?? null,
            'payroll_group' => $data['payroll_group'] ?? null,
            'immediate_supervisor' => $managerName ?? $onboarding->assigned_manager,
            'department_head' => $managerName ?? $onboarding->assigned_manager,
            'manager_id' => $managerId,
        ]);

        // Sync mutable assignment fields on repeat submissions.
        // Re-hiring (or re-submitting) always restores company access: a
        // previously terminated row with the same company email is
        // reactivated instead of lingering as a dead duplicate.
        $wasNewHire = $user->wasRecentlyCreated;
        $previousSalary = $user->getOriginal('basic_salary');
        $user->fill([
            'is_active' => true,
            'employment_status' => $user->employment_status === 'Terminated' ? null : $user->employment_status,
            'position' => $systemPosition,
            'department' => $departmentName,
            'hrm_department_id' => $deptId,
            'hrm_position_id' => $positionModel?->id ?? $user->hrm_position_id,
            'hrm_employment_type_id' => $employmentTypeId ?? $user->hrm_employment_type_id,
            'join_date' => $data['start_date'] ?? $user->join_date,
            'training_start_date' => $deploymentStart,
            'training_end_date' => $trainingEnd,
            'training_duration_weeks' => $durationWeeks,
            'work_location' => $data['work_location'] ?? $user->work_location,
            'work_schedule' => $schedule ?: $user->work_schedule,
            'shift' => $data['shift'] ?? $user->shift,
            'basic_salary' => $data['basic_salary'] ?? $user->basic_salary,
            'payroll_group' => $data['payroll_group'] ?? $user->payroll_group,
            'immediate_supervisor' => $managerName ?? $user->immediate_supervisor,
            'manager_id' => ($managerId && $managerId !== (int) $user->id) ? $managerId : $user->manager_id,
        ])->save();

        // Seed the salary ledger: first hire records "Initial salary", and
        // any re-hire that changes the figure records the movement.
        if ($wasNewHire || (string) ($user->basic_salary ?? '') !== (string) ($previousSalary ?? '')) {
            SalaryHistory::create([
                'user_id' => $user->id,
                'previous_salary' => $wasNewHire ? null : $previousSalary,
                'new_salary' => $user->basic_salary,
                'effective_date' => $data['start_date'] ?? now()->toDateString(),
                'reason' => $wasNewHire ? 'Initial salary' : 'Re-hire salary update',
                'source' => 'initial',
                'recorded_by' => $request->user()?->id,
            ]);
        }

        // Explicit HR re-hire lifts the terminated applicant-portal block.
        TerminatedAccess::restoreApplicantsFor($user->fresh());

        // Push assignment back onto the onboarding record.
        $onboarding->update([
            'position' => $data['position'],
            'department' => $departmentName,
            'employment_type' => $data['employment_type'] ?? $onboarding->employment_type,
            'start_date' => $data['start_date'],
            'assigned_manager' => $managerName ?? $onboarding->assigned_manager,
            'work_location' => $data['work_location'] ?? $onboarding->work_location,
            'work_schedule' => $schedule ?: $onboarding->work_schedule,
            'salary_range' => isset($data['basic_salary']) ? '₱' . number_format((float) $data['basic_salary'], 2) : $onboarding->salary_range,
            'status' => 'Completed', 'progress' => 100,
        ]);

        // Connect to the Shift page roster: attach the new hire to the
        // selected shift (by name) so Shift counts + detail stay in sync.
        if (! empty($data['shift'])) {
            $rosterShift = \App\Models\Hrm\HrmShift::where('name', $data['shift'])->first();
            if ($rosterShift) {
                $rosterShift->employees()->syncWithoutDetaching([$user->id]);
            }
        }

        // Close the loop on the applicant side.
        $app = $person ?? ($onboarding->applicant_id ? Applicant::find($onboarding->applicant_id) : null);
        if ($app) {
            $previousStatus = $app->status;
            $app->update(array_filter([
                'status' => 'Hired', 'archived' => false, 'hired_user_id' => $user->id,
                'emergency_name' => $data['emergency_contact_name'] ?? null,
                'emergency_relationship' => $data['emergency_contact_relationship'] ?? null,
                'emergency_phone' => $data['emergency_contact_number'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''));
            // Ensure hired flag even when emergency fields were empty.
            if ($app->status !== 'Hired') {
                $app->update(['status' => 'Hired', 'archived' => false, 'hired_user_id' => $user->id]);
            }
            \App\Models\Hrm\ApplicantJobApplication::where('applicant_id', $app->id)
                ->whereNotIn('status', ['Withdrawn', 'Rejected'])
                ->update(['status' => 'Hired']);
            ApplicantStatusHistory::create([
                'applicant_id' => $app->id, 'from_status' => $previousStatus,
                'to_status' => 'Hired', 'changed_by' => auth()->id(),
                'reason' => 'Employee account created: ' . $user->email,
            ]);
        }

        // Assign the new hire to Training & Evaluation with status Scheduled:
        // one "Enrolled" enrollment per existing training. Display resolves
        // to Scheduled until the training start date flips it to In Progress.
        foreach (HrmTraining::pluck('id') as $trainingId) {
            HrmTrainingEnrollment::firstOrCreate(
                ['training_id' => $trainingId, 'user_id' => $user->id],
                ['status' => 'Enrolled', 'progress' => 0]
            );
        }

        // Email the credentials + training schedule to the personal email
        // (only for brand-new accounts — never re-send another password to
        // an existing account). Outcome is flashed so HR sees exactly what
        // the new hire received (or that sending failed).
        $personalEmail = $user->personal_email ?: $onboarding->email;
        $mailSent = false;
        $mailError = null;
        if ($user->wasRecentlyCreated && $personalEmail && $personalEmail !== $user->email) {
            try {
                Mail::to($personalEmail)->send(new EmployeeWelcomeMail(
                    $user->name,
                    $user->employee_id,
                    $data['position'],
                    $departmentName,
                    $user->email,
                    $tempPassword,
                    \Carbon\Carbon::parse($data['start_date'])->format('F j, Y'),
                    route('login')
                ));
                $mailSent = true;
            } catch (\Throwable $e) {
                $mailError = $e->getMessage();
                Log::warning('Welcome email failed for user #' . $user->id . ': ' . $e->getMessage());
            }
        }

        // Straight to Training: the new hire sits in the training list
        // (Scheduled until their training start date). The generated login
        // pair is flashed once so HR can hand it over directly — the temp
        // password is only known at this point for brand-new accounts.
        return redirect()->route('hrm.trainee.index', ['new_hire' => $user->id])
            ->with('message', "Employee created: {$user->email}. They are now in the training list with status Scheduled.")
            ->with('hire_credentials', [
                'email' => $user->email,
                'employee_id' => $user->employee_id,
                'password' => $user->wasRecentlyCreated ? $tempPassword : null,
                'is_new_account' => (bool) $user->wasRecentlyCreated,
                'mailed' => $mailSent,
                'mailed_to' => $mailSent ? $personalEmail : null,
                'mail_error' => $mailError,
            ]);
    }

    /**
     * Auto-generate a unique company email: firstname.lastname@monti.com
     * (appends a number while the address is taken).
     */
    protected static function generateCompanyEmail(?string $first, ?string $last): string
    {
        $domain = (string) config('hr.company_email_domain', 'monti.com');
        $local = trim(Str::slug((string) $first) . '.' . Str::slug((string) $last), '.');
        if ($local === '') {
            $local = 'employee';
        }
        $email = "{$local}@{$domain}";
        $i = 1;
        while (User::where('email', $email)->exists()) {
            $i++;
            $email = "{$local}{$i}@{$domain}";
        }

        return $email;
    }

    /**
     * Personal-information payload shaped exactly as
     * OnboardingDetail.vue renders it (photo, full_name, email, phone,
     * birth_date, gender, civil_status, address, zip_code, education,
     * emergency_contact). Falls back to the onboarding record itself so
     * something always shows.
     */
    private function profilePayload(Applicant $person, HrmOnboarding $onboarding): array
    {
        $photoPath = $person->profile_photo_path ?: $person->image;
        $address = trim(implode(', ', array_filter([
            $person->street_address, $person->barangay, $person->city, $person->state_province,
        ])));
        $education = trim(implode(' - ', array_filter([
            $person->highest_education ?: ($person->college ? 'College' : null),
            $person->course ?: $person->school ?: $person->college,
        ])));

        return [
            'photo' => $photoPath ? Storage::url($photoPath) : null,
            'full_name' => trim(implode(' ', array_filter([
                $person->first_name, $person->middle_name, $person->last_name, $person->suffix,
            ]))) ?: $onboarding->name,
            'email' => $person->email ?: $onboarding->email,
            'phone' => $person->phone_number ?: $person->contact_number,
            'birth_date' => optional($person->date_of_birth)?->toDateString(),
            'gender' => $person->sex,
            'civil_status' => $person->civil_status,
            'address' => $address ?: null,
            'zip_code' => $person->postal_zip_code,
            'education' => $education ?: null,
            'emergency_contact' => $person->emergency_name ? [
                'name' => $person->emergency_name,
                'relationship' => $person->emergency_relationship,
                'phone' => $person->emergency_phone,
            ] : null,
        ];
    }

    private function recalc(HrmOnboarding $onboarding): void
    {
        if ($onboarding->status === 'Completed') {
            if ((int) $onboarding->progress !== 100) {
                $onboarding->update(['progress' => 100]);
            }

            return;
        }
        // Overall progress tracks Applicant Requirements only — HR
        // Processing / Company Preparation are HR-internal and excluded.
        // Legacy items without a template link count as applicant items.
        $base = $onboarding->items()->where(function ($q) {
            $q->whereNull('template_item_id')
                ->orWhereHas('templateItem', fn ($t) => $t->where('category', 'Applicant Requirements'));
        });
        $total = max(1, (clone $base)->count());
        $done = (clone $base)->where('status', 'Completed')->count();
        $onboarding->update(['progress' => (int) round($done / $total * 100)]);
    }

    /**
     * Job-aware hired check for one onboarding row.
     * Same personal email + different job => NOT hired (button stays visible).
     */
    protected static function alreadyHiredForOnboarding(HrmOnboarding $onboarding, $person): bool
    {
        if (! $onboarding->email) {
            return (bool) ($person?->hired_user_id && User::whereKey($person->hired_user_id)->exists());
        }

        $candidates = User::with(['hrmOrgPosition:id,name', 'hrmDepartment:id,name'])
            ->where('personal_email', $onboarding->email)
            ->orderByDesc('id')
            ->get();
        if ($person?->hired_user_id) {
            $linked = User::with(['hrmOrgPosition:id,name', 'hrmDepartment:id,name'])->find($person->hired_user_id);
            if ($linked && ! $candidates->contains(fn ($u) => (int) $u->id === (int) $linked->id)) {
                $candidates->push($linked);
            }
        }
        if ($candidates->isEmpty()) {
            return false;
        }

        $norm = fn ($v) => strtolower(trim((string) $v));
        $targetPos = $norm($onboarding->position);
        $targetDept = $norm($onboarding->department);

        foreach ($candidates as $u) {
            $userPos = $norm($u->hrmOrgPosition?->name);
            $userDept = $norm($u->hrmDepartment?->name ?: $u->department);
            $posMatch = $targetPos === '' || $userPos === '' ? true : $userPos === $targetPos;
            $deptMatch = $targetDept === '' || $userDept === '' ? true : $userDept === $targetDept;
            // Same job hired when position matches (department as tie-breaker
            // when both are known). Different position => different job.
            if ($userPos !== '' && $targetPos !== '') {
                if ($userPos === $targetPos && ($targetDept === '' || $userDept === '' || $deptMatch)) {
                    return true;
                }
                continue;
            }
            if ($posMatch && $deptMatch) {
                return true;
            }
        }

        return false;
    }

    /**
     * Full-view accounts see every onboarding: HRM role (owns recruitment),
     * CEO role, and higher-ups (secretary / special_officer / vice_president).
     * Other viewers only see rows where they are the assigned manager.
     */
    protected static function isFullOnboardingViewer($viewer): bool    {
        if (! $viewer) {
            return false;
        }
        if (strtoupper((string) ($viewer->role ?? '')) === 'CEO') {
            return true;
        }
        if (in_array(strtolower((string) ($viewer->position ?? '')), ['secretary', 'special_officer', 'vice_president'], true)) {
            return true;
        }
        if (strtoupper((string) ($viewer->role ?? '')) === 'HRM') {
            return true;
        }

        return false;
    }

    protected static function authorizeOnboardingAccess($viewer, HrmOnboarding $onboarding): void
    {
        if (self::isFullOnboardingViewer($viewer)) {
            return;
        }
        $assigned = strtolower(trim((string) ($onboarding->assigned_manager ?? '')));
        $mine = strtolower(trim((string) ($viewer?->name ?? '')));
        if ($assigned === '' || $assigned !== $mine) {
            abort(403, 'You can only view onboardings assigned to you as reporting manager.');
        }
    }

    /**
     * Group onboarding items the way OnboardingDetail.vue renders them.
     */
    private function sections(HrmOnboarding $onboarding): array
    {
        $badge = fn ($s) => match ($s) {
            'Completed', 'Verified' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'Submitted', 'Resubmitted' => 'bg-blue-50 text-blue-600 border-blue-200',
            'Under Review', 'In Progress' => 'bg-amber-50 text-amber-700 border-amber-200',
            'Rejected', 'Blocked' => 'bg-rose-50 text-rose-600 border-rose-200',
            default => 'bg-slate-50 text-slate-500 border-slate-200',
        };

        $sections = [];
        foreach ($onboarding->items as $item) {
            $ti = $item->templateItem;
            $sections[$ti?->category ?? 'General Requirements'][] = [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $ti?->description,
                'is_required' => (bool) ($ti?->is_required ?? true),
                'input_type' => strtolower((string) ($ti?->input_type ?? 'file')),
                'options' => $ti?->options ?? null,
                'applicant_visible' => (bool) ($ti?->applicant_visible ?? true),
                'is_task' => $ti && $ti->responsible_party && $ti->responsible_party !== 'Applicant',
                'responsible_party' => $ti?->responsible_party,
                'status' => $item->status,
                'status_badge' => $badge($item->status),
                'due_date' => optional($item->due_date)?->toDateString(),
                'submitted_at' => optional($item->submitted_at)?->toDateTimeString(),
                'rejection_reason' => $item->rejection_reason,
                'latest_submission' => ($item->file_url || $item->value || $item->submitted_at) ? [
                    'file_url' => $item->file_url ? Storage::url($item->file_url) : null,
                    'original_name' => $item->original_name,
                    'value' => $item->value,
                    'submitted_at' => optional($item->submitted_at)?->toDateTimeString(),
                ] : null,
                'submissions' => [],
            ];
        }

        return $sections;
    }
}
