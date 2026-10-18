<?php

namespace App\Http\Controllers\Hrm\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\ApplicantJobApplication;
use App\Models\Hrm\ApplicantStatusHistory;
use App\Models\Hrm\HrmInterview;
use App\Models\Hrm\HrmInterviewEvaluation;
use App\Models\Hrm\HrmJobPosting;
use App\Models\Hrm\HrmOnboarding;
use App\Support\ReapplyCooldown;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InterviewController extends Controller
{
    use HasPagePermissions;

    public function index()
    {
        return Inertia::render('Dashboard/HRM_NEW/Interviews', [
            'applicants' => Applicant::with('jobPosting')->whereIn('status', ['Shortlisted', 'Interview'])->latest()->take(50)->get(),
            'metrics' => [
                'total' => Applicant::count(),
                'shortlisted' => Applicant::where('status', 'Shortlisted')->count(),
                'interview_scheduled' => HrmInterview::where('status', 'Scheduled')->count(),
                'interviewed' => HrmInterview::where('status', 'Completed')->count(),
            ],
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function list()
    {
        return response()->json(HrmInterview::with(['evaluation', 'applicant', 'interviewer:id,name,email'])->latest()->paginate(20));
    }

    /**
     * Only employees who can actually open HRM > Interview are eligible
     * interviewers. Mirrors CheckPagePermission / User::hasPagePermission
     * for module HRM, page interview (view):
     * - explicit 'disabled' row always excludes (even CEO / natives)
     * - CEO includes (full edit everywhere)
     * - secretary / special_officer include (superusers)
     * - HRM-native manager/staff with no explicit HRM grants include
     * - anyone with an explicit HRM/interview view|edit grant includes
     */
    public function interviewers()
    {
        $users = User::with([
            'pagePermissions' => fn ($q) => $q->whereIn('module', ['HRM', 'hrm', 'Hrm']),
            'hrmOrgPosition:id,name,code',
            'hrmDepartment:id,name',
        ])
            ->where('is_active', '!=', 0)
            ->select('id', 'name', 'email', 'role', 'position', 'department', 'hrm_position_id', 'hrm_department_id', 'is_active')
            ->orderBy('name')
            ->take(200)->get();

        $eligible = $users->filter(fn ($u) => $this->canAccessInterview($u))->values()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'position' => $u->position,
                'job_position' => $u->hrmOrgPosition?->name ?: ucfirst((string) $u->position),
                'department' => $u->hrmDepartment?->name ?: $u->department,
            ])
            // Same person can hold multiple user rows (e.g. duplicate accounts)
            // — show each person only once in the dropdown (keep lowest id).
            ->sortBy('id')
            ->unique(fn ($u) => mb_strtolower(trim((string) $u['name'])))
            ->values();

        return response()->json($eligible);
    }

    private function canAccessInterview(User $user): bool
    {
        $perms = $user->relationLoaded('pagePermissions')
            ? $user->pagePermissions
            : $user->pagePermissions()->whereIn('module', ['HRM', 'hrm', 'Hrm'])->get();

        $interviewRow = $perms->first(fn ($r) => strtolower((string) $r->page) === 'interview');
        // Explicit disable (set by IT) always wins.
        if ($interviewRow && strtolower((string) ($interviewRow->permission_level ?? 'edit')) === 'disabled') {
            return false;
        }

        // CEO + superusers have full access.
        if ($user->role === 'CEO') {
            return true;
        }
        if (in_array($user->position, ['secretary', 'special_officer'], true)) {
            return true;
        }

        // HRM-native manager/staff: full access unless they have any
        // explicit HRM grants (then grants are the exact access set).
        if (strtoupper((string) $user->role) === 'HRM' && in_array($user->position, ['manager', 'staff'], true)) {
            if ($perms->isEmpty()) {
                return true;
            }
            // Fall through to strict row check.
        }

        if ($interviewRow === null) {
            return false;
        }
        $granted = strtolower((string) ($interviewRow->permission_level ?? 'edit'));

        return in_array($granted, ['view', 'edit'], true);
    }

    private function ensureInterviewerEligible(?int $interviewerId): void
    {
        if (! $interviewerId) {
            return;
        }
        $interviewer = User::with(['pagePermissions' => fn ($q) => $q->whereIn('module', ['HRM', 'hrm', 'Hrm'])])->find($interviewerId);
        if (! $interviewer || ! $this->canAccessInterview($interviewer)) {
            abort(response()->json(['message' => 'Selected interviewer does not have access to HRM Interview.', 'errors' => ['interviewer_id' => ['Selected interviewer does not have access to HRM Interview.']]], 422));
        }
    }

    public function fetch()
    {
        return response()->json(HrmInterview::with(['evaluation', 'applicant', 'interviewer:id,name,email'])->latest()->take(50)->get());
    }

    /**
     * Upcoming scheduled interviews (ported from super_monti).
     */
    public function upcoming()
    {
        return response()->json(
            HrmInterview::with(['applicant', 'evaluation', 'interviewer:id,name,email'])
                ->where('status', 'Scheduled')
                ->whereDate('date', '>=', today())
                ->orderBy('date')->orderBy('start_time')
                ->get()
        );
    }

    /**
     * Single interview with relations (ported from super_monti).
     */
    public function show(HrmInterview $interview)
    {
        return response()->json(
            $interview->loadMissing(['applicant', 'interviewer', 'evaluation', 'jobPosting'])
        );
    }

    /**
     * Generic interview update with workflow side-effects: completing marks
     * the applicant Interviewed, a Passed result marks them Selected
     * (ported from super_monti, adapted to applicants schema).
     */
    public function update(Request $request, HrmInterview $interview)
    {
        $data = $request->validate([
            'type' => 'nullable|in:Initial,Final,Initial Interview,Final Interview',
            'method' => 'nullable|in:Online,Onsite',
            'location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|string',
            'date' => 'nullable|date|after_or_equal:today',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'interviewer_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:5000',
            'status' => 'nullable|in:Scheduled,Completed,Cancelled,No Show,Rescheduled',
            'result' => 'nullable|in:Passed,Failed,Pending',
        ]);
        if (isset($data['type'])) {
            $data['type'] = stripos($data['type'], 'final') !== false ? 'Final' : 'Initial';
        }
        $this->ensureInterviewerEligible($data['interviewer_id'] ?? null);
        $result = $data['result'] ?? null;
        unset($data['result']);

        DB::transaction(function () use ($interview, $data, $result, $request) {
            $interview->update($data);
            $app = $interview->applicant;
            if (! $app) {
                return;
            }
            if (($data['status'] ?? null) === 'Completed') {
                $from = $app->status;
                $app->update(['status' => 'Interviewed']);
                ApplicantJobApplication::where('applicant_id', $app->id)
                    ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
                    ->update(['status' => 'Interviewed']);
                ApplicantStatusHistory::create([
                    'applicant_id' => $app->id, 'from_status' => $from,
                    'to_status' => 'Interviewed', 'changed_by' => $request->user()->id,
                    'reason' => 'Interview #' . $interview->id . ' completed',
                ]);
            }
            if ($result === 'Passed') {
                $from = $app->fresh()->status;
                $app->update(['status' => 'Selected']);
                ApplicantJobApplication::where('applicant_id', $app->id)
                    ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
                    ->update(['status' => 'Selected']);
                ApplicantStatusHistory::create([
                    'applicant_id' => $app->id, 'from_status' => $from,
                    'to_status' => 'Selected', 'changed_by' => $request->user()->id,
                    'reason' => 'Passed interview #' . $interview->id,
                ]);
            } elseif ($result === 'Failed') {
                // Failed Initial/Final interview: reject that job and start
                // the 6-month same-job re-application cooldown.
                $from = $app->fresh()->status;
                $fresh = $interview->fresh();
                $postingId = $fresh->job_posting_id
                    ?? ($app->job_posting_id ?? null);
                $reason = 'Failed ' . ($fresh->type ?? 'interview') . ' #' . $interview->id;
                ReapplyCooldown::recordFailure((int) $app->id, $postingId, (string) ($fresh->type ?? 'Initial'), $reason);
                $app->update(['status' => 'Rejected', 'rejection_reason' => $reason]);
                ApplicantStatusHistory::create([
                    'applicant_id' => $app->id, 'from_status' => $from,
                    'to_status' => 'Rejected', 'changed_by' => $request->user()->id,
                    'reason' => $reason,
                ]);
            }
        });

        return response()->json(['ok' => true, 'data' => $interview->fresh()->loadMissing(['applicant', 'evaluation'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'application_id' => 'nullable|exists:applicants,id',
            'applicant_id' => 'nullable|exists:applicants,id',
            'job_posting_id' => 'nullable|exists:hrm_job_postings,id',
            'candidate_name' => 'nullable|string',
            'position' => 'nullable|string',
            'type' => 'nullable|in:Initial,Final,Initial Interview,Final Interview',
            'interviewer_id' => 'nullable|exists:users,id',
            'date' => 'nullable|date',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'method' => 'nullable|in:Online,Onsite',
            'location' => 'nullable|string',
            'meeting_link' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        $data['applicant_id'] ??= $data['application_id'] ?? null;
        // Keep the job link: without it the onboarding handoff can't tell
        // which of the applicant's jobs advanced (all interviews had null).
        if (empty($data['job_posting_id']) && ! empty($data['applicant_id'])) {
            $data['job_posting_id'] = $this->resolvePostingId((int) $data['applicant_id']);
        }
        $this->ensureInterviewerEligible($data['interviewer_id'] ?? null);
        // Normalize display values ("Initial Interview"/"Final Interview") to DB convention (Initial|Final).
        if (isset($data['type'])) {
            $data['type'] = stripos($data['type'], 'final') !== false ? 'Final' : 'Initial';
        }
        $interview = HrmInterview::create($data + ['status' => 'Scheduled']);
        if ($interview->applicant_id) {
            Applicant::whereKey($interview->applicant_id)->update(['status' => 'Interview']);
            ApplicantJobApplication::where('applicant_id', $interview->applicant_id)
                ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
                ->update(['status' => 'Interview']);
            if ($app = Applicant::find($interview->applicant_id)) {
                \App\Support\NotifiesApplicant::push($app, 'interview', 'Interview Scheduled', 'Your ' . ($interview->type ?? '') . ' interview has been scheduled.', [
                    'position' => $interview->position,
                    'interview_type' => $interview->type,
                    'date' => optional($interview->date)?->toDateString(),
                    'start_time' => $interview->start_time,
                    'end_time' => $interview->end_time,
                    'method' => $interview->method,
                    'location' => $interview->location,
                    'meeting_link' => $interview->meeting_link,
                    'notes' => $interview->notes,
                    'status' => 'Scheduled',
                ]);
            }
        }

        return response()->json($interview, 201);
    }

    public function reschedule(Request $request, HrmInterview $interview)
    {
        $interview->update($request->validate([
            'date' => 'sometimes|date', 'start_time' => 'sometimes|string',
            'end_time' => 'sometimes|string', 'reason' => 'nullable|string',
        ]));

        return response()->json($interview);
    }

    /**
     * Interviewer approves an applicant's pending reschedule request:
     * the live schedule adopts the proposed date/time and the request clears.
     */
    public function approveReschedule(Request $request, HrmInterview $interview)
    {
        if ($interview->reschedule_status !== 'requested') {
            return response()->json(['message' => 'There is no pending reschedule request for this interview.'], 422);
        }

        $interview->update([
            'date' => $interview->reschedule_date,
            'start_time' => $interview->reschedule_start_time ?: $interview->start_time,
            'end_time' => $interview->reschedule_end_time ?: $interview->end_time,
            'status' => 'Scheduled',
            'reschedule_status' => null,
            'reschedule_date' => null,
            'reschedule_start_time' => null,
            'reschedule_end_time' => null,
            'reschedule_reason' => null,
            'reschedule_requested_at' => null,
        ]);

        if ($app = $interview->applicant) {
            \App\Support\NotifiesApplicant::push($app, 'interview', 'Reschedule Approved', 'Your interview has been moved to the requested schedule.', [
                'position' => $interview->position,
                'interview_type' => $interview->type,
                'date' => optional($interview->date)?->toDateString(),
                'start_time' => $interview->start_time,
                'end_time' => $interview->end_time,
                'method' => $interview->method,
                'location' => $interview->location,
                'meeting_link' => $interview->meeting_link,
                'status' => 'Scheduled',
            ]);
        }

        return response()->json(['ok' => true, 'data' => $interview->fresh()->loadMissing(['applicant', 'evaluation', 'interviewer:id,name,email'])]);
    }

    /**
     * Interviewer denies an applicant's pending reschedule request: the
     * original schedule is kept and the request clears.
     */
    public function denyReschedule(Request $request, HrmInterview $interview)
    {
        if ($interview->reschedule_status !== 'requested') {
            return response()->json(['message' => 'There is no pending reschedule request for this interview.'], 422);
        }

        $interview->update([
            'reschedule_status' => null,
            'reschedule_date' => null,
            'reschedule_start_time' => null,
            'reschedule_end_time' => null,
            'reschedule_reason' => null,
            'reschedule_requested_at' => null,
        ]);

        if ($app = $interview->applicant) {
            \App\Support\NotifiesApplicant::push($app, 'interview', 'Reschedule Not Approved', 'Your interview will proceed on the original schedule.', [
                'position' => $interview->position,
                'interview_type' => $interview->type,
                'date' => optional($interview->date)?->toDateString(),
                'start_time' => $interview->start_time,
                'end_time' => $interview->end_time,
                'status' => 'Scheduled',
            ]);
        }

        return response()->json(['ok' => true, 'data' => $interview->fresh()->loadMissing(['applicant', 'evaluation', 'interviewer:id,name,email'])]);
    }

    public function cancel(Request $request, HrmInterview $interview)
    {
        $interview->update([
            'status' => 'Cancelled',
            'cancelled_by' => 'hrm',
            'cancel_reason' => $request->get('reason'),
            'reschedule_status' => null,
            'reschedule_date' => null,
            'reschedule_start_time' => null,
            'reschedule_end_time' => null,
            'reschedule_reason' => null,
            'reschedule_requested_at' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function noShow(HrmInterview $interview)
    {
        $interview->update(['status' => 'No Show']);

        return response()->json(['ok' => true]);
    }

    public function complete(Request $request, HrmInterview $interview)
    {
        $request->validate([
            'result' => 'nullable|in:passed,failed,pending_review',
            'recommendation' => 'nullable|in:proceed_to_final_interview,proceed_to_onboarding,proceed_to_onboarding_training,recommend_onboarding_training,do_not_proceed,further_assessment_required,reject',
        ]);
        $interview->update(['status' => 'Completed']);
        $evaluation = HrmInterviewEvaluation::updateOrCreate(['interview_id' => $interview->id], $request->only([
            'communication_skills', 'relevant_skills', 'technical_knowledge', 'problem_solving',
            'work_experience', 'adaptability', 'teamwork', 'professionalism',
            'strengths', 'concerns', 'interview_notes', 'result', 'recommendation',
        ]) + ['evaluated_by' => $request->user()->id]);

        // Act on the recommendation immediately on submit:
        // - onboarding recommendations move the applicant straight to HR onboarding;
        // - reject marks the applicant Rejected (other applications untouched).
        // A failed Initial/Final interview starts the 6-month same-job
        // re-application cooldown on that job only (ReapplyCooldown).
        $recommendation = $evaluation->recommendation;
        $result = $evaluation->result;
        $advanced = false;
        $rejected = false;
        if (in_array($recommendation, ['proceed_to_onboarding', 'proceed_to_onboarding_training', 'recommend_onboarding_training'], true)) {
            $this->moveInterviewToOnboarding($interview, $request->user()->id);
            $advanced = true;
        } elseif ($result === 'failed' || in_array($recommendation, ['reject', 'do_not_proceed'], true)) {
            if ($app = $interview->applicant) {
                $postingId = $interview->job_posting_id
                    ?? ($interview->applicant_id ? $this->resolvePostingId((int) $interview->applicant_id) : null);
                $from = $app->status;
                $reason = 'Failed ' . ($interview->type ?? 'interview') . ' #' . $interview->id;
                ReapplyCooldown::recordFailure((int) $app->id, $postingId, (string) ($interview->type ?? 'Initial'), $reason);
                $app->update(['status' => 'Rejected', 'rejection_reason' => 'Rejected after ' . ($interview->type ?? 'interview') . ' evaluation #' . $interview->id]);
                ApplicantStatusHistory::create([
                    'applicant_id' => $app->id, 'from_status' => $from,
                    'to_status' => 'Rejected', 'changed_by' => $request->user()->id,
                    'reason' => 'Rejected after interview #' . $interview->id,
                ]);
                \App\Support\NotifiesApplicant::push($app, 'rejection', 'Application Update', 'Thank you for your interest. A decision has been made on your application — you may re-apply to this position after 6 months.', ['position' => $app->position_applied, 'status' => 'Rejected']);
                $rejected = true;
            }
        }

        return response()->json(['ok' => true, 'advanced_to_onboarding' => $advanced, 'rejected' => $rejected]);
    }

    public function advanceToOnboarding(HrmInterview $interview)
    {
        $onboarding = $this->moveInterviewToOnboarding($interview, auth()->id());

        return response()->json($onboarding, 201);
    }

    /**
     * Shared onboarding handoff used by both the manual "Advance to
     * Onboarding" button and the automatic move when an evaluation is
     * submitted with an onboarding recommendation. Skips creating a
     * duplicate record when one is already open for the same applicant.
     */
    /**
     * Which job posting an applicant's interview belongs to: latest
     * non-terminal job application first, then the applicant header link.
     */
    private function resolvePostingId(int $applicantId): ?int
    {
        return ApplicantJobApplication::where('applicant_id', $applicantId)
                ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
                ->latest()->value('job_posting_id')
            ?? ApplicantJobApplication::where('applicant_id', $applicantId)
                ->latest()->value('job_posting_id')
            ?? Applicant::whereKey($applicantId)->value('job_posting_id');
    }

    private function moveInterviewToOnboarding(HrmInterview $interview, $changedBy): HrmOnboarding
    {
        $app = $interview->applicant;
        // Resolve the job when old interviews carry none, so each applied job
        // gets its own onboarding row instead of merging into the first one.
        $postingId = $interview->job_posting_id
            ?? ($interview->applicant_id ? $this->resolvePostingId((int) $interview->applicant_id) : null);
        if ($postingId && ! $interview->job_posting_id) {
            $interview->update(['job_posting_id' => $postingId]);
        }
        $existingQuery = HrmOnboarding::where('applicant_id', $interview->applicant_id)
            ->where('status', '!=', 'Completed');
        if ($postingId) {
            $existingQuery->where('job_posting_id', $postingId);
        }
        if ($existing = $existingQuery->latest()->first()) {
            $onboarding = $existing;
            // Backfill department on rows created before it was stored.
            if (! $onboarding->department && $postingId) {
                $onboarding->department = HrmJobPosting::whereKey($postingId)->value('department');
                if ($onboarding->isDirty('department')) {
                    $onboarding->save();
                }
            }
        } else {
            // Scope the template to the hired posting so position-scoped
            // templates (e.g. an HR Staff template) win for that position
            // while other hires keep the generic default.
            $posting = $postingId ? HrmJobPosting::find($postingId) : null;
            $departmentId = null;
            if ($posting?->department) {
                $departmentId = \App\Models\Hrm\HrmDepartment::where('name', $posting->department)->value('id');
            }
            $template = \App\Support\OnboardingSnapshot::defaultTemplate([
                'department_id' => $departmentId,
                'position_id' => $posting?->position_id,
                'employment_type_id' => $posting?->employment_type_id,
                'work_mode' => $posting?->work_mode,
            ]);
            $onboarding = HrmOnboarding::create([
                'applicant_id' => $interview->applicant_id,
                'job_posting_id' => $postingId,
                'template_id' => $template?->id,
                'name' => $interview->candidate_name ?? $app?->first_name . ' ' . $app?->last_name,
                'email' => $app?->email,
                'position' => $interview->position,
                'department' => $postingId ? HrmJobPosting::whereKey($postingId)->value('department') : null,
                'status' => 'In Progress',
            ]);
            // Materialize the template so HR + applicant portal both see the items.
            if ($template) {
                \App\Support\OnboardingSnapshot::apply($onboarding, $template);
            }
        }
        // Close the loop: interview done, applicant now in onboarding.
        $interview->update(['status' => 'Completed']);
        if ($app) {
            $from = $app->status;
            $app->update(['status' => 'Onboarding', 'archived' => false]);
            // Flip only this job's application — never sibling applications.
            $jaQuery = ApplicantJobApplication::where('applicant_id', $app->id)
                ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired']);
            if ($postingId) {
                $jaQuery->where('job_posting_id', $postingId);
            }
            $jaQuery->update(['status' => 'Onboarding']);
            ApplicantStatusHistory::create([
                'applicant_id' => $app->id, 'from_status' => $from,
                'to_status' => 'Onboarding', 'changed_by' => $changedBy,
                'reason' => 'Advanced to onboarding #' . $onboarding->id,
            ]);
        }

        return $onboarding;
    }

    public function select(Request $request, HrmInterview $interview)
    {
        $interview->update(['status' => 'Completed']);
        if ($app = $interview->applicant) {
            $from = $app->status;
            $app->update(['status' => 'Offered']);
            ApplicantJobApplication::where('applicant_id', $app->id)
                ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
                ->update(['status' => 'Offered']);
            ApplicantStatusHistory::create([
                'applicant_id' => $app->id, 'from_status' => $from,
                'to_status' => 'Offered', 'changed_by' => $request->user()->id,
                'reason' => 'Selected after interview #' . $interview->id,
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
