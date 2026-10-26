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
use App\Services\Hrm\ApplicantApplicationHoldService;
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
        $applicants = Applicant::with('jobPosting')
            ->whereHas('jobApplications', function ($query) {
                $query->whereIn('status', ['Shortlisted', 'Interview'])->notOnHold();
            })
            ->latest();

        return Inertia::render('Dashboard/HRM_NEW/Interviews', [
            'applicants' => $applicants->take(50)->get(),
            'metrics' => [
                'total' => Applicant::count(),
                'shortlisted' => Applicant::where('status', 'Shortlisted')->count(),
                'interview_scheduled' => ApplicantApplicationHoldService::excludeHeldInterviews(
                    HrmInterview::where('status', 'Scheduled')
                )->count(),
                'interviewed' => ApplicantApplicationHoldService::excludeHeldInterviews(
                    HrmInterview::where('status', 'Completed')
                )->count(),
                'interview_rescheduled' => ApplicantApplicationHoldService::excludeHeldInterviews(
                    HrmInterview::where('status', 'Rescheduled')
                )->count(),
            ],
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function list()
    {
        return response()->json(
            ApplicantApplicationHoldService::excludeHeldInterviews(
                HrmInterview::with(['evaluation', 'applicant', 'interviewer:id,name,email'])
            )->latest()->paginate(20)
        );
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
    public function interviewers(Request $request)
    {
        $department = $request->query('department');

        $query = User::with([
            'pagePermissions' => fn ($q) => $q->whereIn('module', ['HRM', 'hrm', 'Hrm']),
            'hrmOrgPosition:id,name,code',
            'hrmDepartment:id,name,code',
        ])
            ->where('is_active', '!=', 0)
            ->select('id', 'name', 'email', 'role', 'position', 'department', 'hrm_position_id', 'hrm_department_id', 'is_active')
            ->orderBy('name')
            ->take(200);

        if ($department) {
            $query->where(function ($q) use ($department) {
                $q->whereHas('hrmDepartment', function ($dept) use ($department) {
                    $dept->whereRaw('LOWER(name) = ?', [strtolower($department)])
                        ->orWhereRaw('LOWER(code) = ?', [strtolower($department)])
                        ->orWhereRaw('LOWER(category) = ?', [strtolower($department)]);
                })->orWhereRaw('LOWER(department) = ?', [strtolower($department)]);
            });
        }

        $users = $query->get();

        $eligible = $users->values()
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

        // Filter to only managers if department is specified
        if ($department) {
            $eligible = $eligible->filter(fn ($u) =>
                str_contains(strtolower((string) ($u['job_position'] ?? '')), 'manager') ||
                strtolower((string) ($u['position'] ?? '')) === 'manager'
            )->values();
        }

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
        $interviewer = User::with([
            'pagePermissions' => fn ($q) => $q->whereIn('module', ['HRM', 'hrm', 'Hrm']),
            'hrmOrgPosition:id,name',
        ])->find($interviewerId);
        $isManager = $interviewer && (
            strtolower((string) $interviewer->position) === 'manager'
            || str_contains(strtolower((string) $interviewer->hrmOrgPosition?->name), 'manager')
        );
        if (! $interviewer || (! $isManager && ! $this->canAccessInterview($interviewer))) {
            abort(response()->json(['message' => 'Selected interviewer is not eligible.', 'errors' => ['interviewer_id' => ['Selected interviewer is not eligible.']]], 422));
        }
    }

    public function fetch()
    {
        return response()->json(
            ApplicantApplicationHoldService::excludeHeldInterviews(
                HrmInterview::with(['evaluation', 'applicant', 'interviewer:id,name,email'])
            )->latest()->take(50)->get()
        );
    }

    /**
     * Upcoming scheduled interviews (ported from super_monti).
     */
    public function upcoming()
    {
        return response()->json(
            ApplicantApplicationHoldService::excludeHeldInterviews(
                HrmInterview::with(['applicant', 'evaluation', 'interviewer:id,name,email'])
                    ->where('status', 'Scheduled')
                    ->whereDate('date', '>=', today())
                    ->orderBy('date')->orderBy('start_time')
            )->get()
        );
    }

    /**
     * Single interview with relations (ported from super_monti).
     */
    public function show(HrmInterview $interview)
    {
        if ($interview->applicant_id
            && ApplicantApplicationHoldService::isHeldFor((int) $interview->applicant_id, $interview->job_posting_id)) {
            abort(404);
        }

        return response()->json(
            $interview->loadMissing(['applicant', 'interviewer', 'evaluation', 'jobPosting'])
        );
    }

    /**
     * Reject mutations that target an application parked by the onboarding hold.
     */
    private function assertInterviewNotHeld(HrmInterview $interview): ?\Illuminate\Http\JsonResponse
    {
        if (! $interview->applicant_id
            || ! ApplicantApplicationHoldService::isHeldFor((int) $interview->applicant_id, $interview->job_posting_id)) {
            return null;
        }

        return response()->json(['message' => ApplicantApplicationHoldService::HOLD_MESSAGE], 422);
    }

    /**
     * Generic interview update with workflow side-effects: completing marks
     * the applicant Interviewed, a Passed result marks them Selected
     * (ported from super_monti, adapted to applicants schema).
     */
    public function update(Request $request, HrmInterview $interview)
    {
        if ($response = $this->assertInterviewNotHeld($interview)) {
            return $response;
        }

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
                    ->notOnHold()
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
                    ->notOnHold()
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
        if (! empty($data['applicant_id'])
            && ApplicantApplicationHoldService::isHeldFor((int) $data['applicant_id'], $data['job_posting_id'] ?? null)) {
            return response()->json(['message' => ApplicantApplicationHoldService::HOLD_MESSAGE], 422);
        }
        $this->ensureInterviewerEligible($data['interviewer_id'] ?? null);
        // Normalize display values ("Initial Interview"/"Final Interview") to DB convention (Initial|Final).
        if (isset($data['type'])) {
            $data['type'] = stripos($data['type'], 'final') !== false ? 'Final' : 'Initial';
        }

        // Idempotency: a repeated submit (double-click, retry, refresher) for
        // the exact same slot must not create a second "Scheduled" row. Reuse
        // the existing one so the dashboard never shows duplicates.
        if (! empty($data['applicant_id'])) {
            $existing = HrmInterview::query()
                ->where('applicant_id', $data['applicant_id'])
                ->where('type', $data['type'] ?? 'Initial')
                ->where('job_posting_id', $data['job_posting_id'] ?? null)
                ->where('date', $data['date'] ?? null)
                ->where('start_time', $data['start_time'] ?? null)
                ->where('status', 'Scheduled')
                ->first();

            if ($existing) {
                $existing->update($data);

                return response()->json(
                    $existing->fresh()->loadMissing(['applicant', 'evaluation', 'interviewer:id,name,email']),
                    200
                );
            }
        }

        $interview = HrmInterview::create($data + ['status' => 'Scheduled']);
        if ($interview->applicant_id) {
            Applicant::whereKey($interview->applicant_id)->update(['status' => 'Interview']);
            ApplicantJobApplication::where('applicant_id', $interview->applicant_id)
                ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
                ->notOnHold()
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
        if ($response = $this->assertInterviewNotHeld($interview)) {
            return $response;
        }

        $data = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'reason' => 'nullable|string',
            'interviewer_id' => 'nullable|exists:users,id',
            'method' => 'nullable|in:Online,Onsite',
            'location' => 'nullable|string',
            'meeting_link' => 'nullable|string',
        ]);

        $this->ensureInterviewerEligible($data['interviewer_id'] ?? null);

        if ($interview->reschedule_status === 'completed') {
            return response()->json(['message' => 'This interview has already been rescheduled.'], 422);
        }

        DB::transaction(function () use ($interview, $data, $request) {
            $oldDate = $interview->date;
            $oldStartTime = $interview->start_time;
            $oldEndTime = $interview->end_time;
            $oldInterviewerId = $interview->interviewer_id;
            $oldMethod = $interview->method;
            $oldLocation = $interview->location;
            $oldMeetingLink = $interview->meeting_link;

            $slot = [
                'applicant_id' => $interview->applicant_id,
                'job_posting_id' => $interview->job_posting_id,
                'type' => $interview->type,
                'date' => $data['date'],
                'start_time' => $data['start_time'],
            ];
            $attributes = [
                'candidate_name' => $interview->candidate_name,
                'position' => $interview->position,
                'interviewer_id' => $data['interviewer_id'] ?? $interview->interviewer_id,
                'end_time' => $data['end_time'],
                'method' => $data['method'] ?? $interview->method,
                'location' => $data['location'] ?? $interview->location,
                'meeting_link' => $data['meeting_link'] ?? $interview->meeting_link,
                'notes' => $interview->notes,
            ];
            // Reuse an already-scheduled slot instead of creating a duplicate row.
            $newInterview = $this->findScheduledSlot($slot, $interview->id);
            if ($newInterview) {
                $newInterview->update($attributes);
            } else {
                $newInterview = HrmInterview::create($slot + $attributes + [
                    'status' => 'Scheduled',
                    'rescheduled_from_id' => $interview->id,
                ]);
            }

            $interview->update([
                'status' => 'Rescheduled',
                'reschedule_status' => 'completed',
                'reschedule_date' => $data['date'],
                'reschedule_start_time' => $data['start_time'],
                'reschedule_end_time' => $data['end_time'],
                'reschedule_reason' => $data['reason'],
                'reschedule_requested_at' => now(),
                'rescheduled_to_id' => $newInterview->id,
            ]);

            if ($app = $interview->applicant) {
                \App\Support\NotifiesApplicant::push($app, 'interview', 'Interview Rescheduled', 'Your interview has been rescheduled.', [
                    'position' => $interview->position,
                    'interview_type' => $interview->type,
                    'old_date' => optional($oldDate)?->toDateString(),
                    'old_start_time' => $oldStartTime,
                    'old_end_time' => $oldEndTime,
                    'date' => $data['date'],
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'method' => $data['method'] ?? $interview->method,
                    'location' => $data['location'] ?? $interview->location,
                    'meeting_link' => $data['meeting_link'] ?? $interview->meeting_link,
                    'status' => 'Rescheduled',
                    'rescheduled_interview_id' => $newInterview->id,
                ]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Interview rescheduled successfully. The previous interview is marked as Rescheduled.',
            'old_interview' => $interview->fresh()->loadMissing(['applicant', 'evaluation', 'interviewer:id,name,email']),
            'new_interview' => HrmInterview::with(['applicant', 'evaluation', 'interviewer:id,name,email'])->find($newInterview->id),
        ]);
    }

    /**
     * Interviewer approves an applicant's pending reschedule request:
     * creates a new interview record with the requested date/time,
     * marks the old interview as Rescheduled.
     */
    public function approveReschedule(Request $request, HrmInterview $interview)
    {
        if ($response = $this->assertInterviewNotHeld($interview)) {
            return $response;
        }

        if ($interview->reschedule_status !== 'requested') {
            return response()->json(['message' => 'There is no pending reschedule request for this interview.'], 422);
        }

        $oldDate = $interview->date;
        $oldStartTime = $interview->start_time;
        $oldEndTime = $interview->end_time;
        $oldInterviewerId = $interview->interviewer_id;
        $oldMethod = $interview->method;
        $oldLocation = $interview->location;
        $oldMeetingLink = $interview->meeting_link;
        $rescheduleReason = $interview->reschedule_reason;

        DB::transaction(function () use ($interview, $oldDate, $oldStartTime, $oldEndTime, $oldInterviewerId, $oldMethod, $oldLocation, $oldMeetingLink, $rescheduleReason) {
            $slot = [
                'applicant_id' => $interview->applicant_id,
                'job_posting_id' => $interview->job_posting_id,
                'type' => $interview->type,
                'date' => $interview->reschedule_date,
                'start_time' => $interview->reschedule_start_time ?: $interview->start_time,
            ];
            $attributes = [
                'candidate_name' => $interview->candidate_name,
                'position' => $interview->position,
                'interviewer_id' => $interview->interviewer_id,
                'end_time' => $interview->reschedule_end_time ?: $interview->end_time,
                'method' => $interview->method,
                'location' => $interview->location,
                'meeting_link' => $interview->meeting_link,
                'notes' => $interview->notes,
            ];
            // Reuse an already-scheduled slot instead of creating a duplicate row.
            $newInterview = $this->findScheduledSlot($slot, $interview->id);
            if ($newInterview) {
                $newInterview->update($attributes);
            } else {
                $newInterview = HrmInterview::create($slot + $attributes + [
                    'status' => 'Scheduled',
                    'rescheduled_from_id' => $interview->id,
                ]);
            }

            $interview->update([
                'status' => 'Rescheduled',
                'reschedule_status' => 'completed',
                'reschedule_date' => $interview->reschedule_date,
                'reschedule_start_time' => $interview->reschedule_start_time ?: $interview->start_time,
                'reschedule_end_time' => $interview->reschedule_end_time ?: $interview->end_time,
                'reschedule_reason' => $interview->reschedule_reason,
                'reschedule_requested_at' => $interview->reschedule_requested_at,
                'rescheduled_to_id' => $newInterview->id,
            ]);

            if ($app = $interview->applicant) {
                \App\Support\NotifiesApplicant::push($app, 'interview', 'Reschedule Approved', 'Your interview has been rescheduled.', [
                    'position' => $interview->position,
                    'interview_type' => $interview->type,
                    'old_date' => optional($oldDate)?->toDateString(),
                    'old_start_time' => $oldStartTime,
                    'old_end_time' => $oldEndTime,
                    'date' => $interview->reschedule_date,
                    'start_time' => $interview->reschedule_start_time ?: $interview->start_time,
                    'end_time' => $interview->reschedule_end_time ?: $interview->end_time,
                    'method' => $interview->method,
                    'location' => $interview->location,
                    'meeting_link' => $interview->meeting_link,
                    'status' => 'Rescheduled',
                    'rescheduled_interview_id' => $newInterview->id,
                ]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Reschedule approved. A new interview has been scheduled and the previous one is marked as Rescheduled.',
            'old_interview' => $interview->fresh()->loadMissing(['applicant', 'evaluation', 'interviewer:id,name,email']),
            'new_interview' => HrmInterview::with(['applicant', 'evaluation', 'interviewer:id,name,email'])->find($newInterview->id),
        ]);
    }

    /**
     * Interviewer denies an applicant's pending reschedule request: the
     * original schedule is kept and the request clears.
     */
    public function denyReschedule(Request $request, HrmInterview $interview)
    {
        if ($response = $this->assertInterviewNotHeld($interview)) {
            return $response;
        }

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
        if ($response = $this->assertInterviewNotHeld($interview)) {
            return $response;
        }

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
        if ($response = $this->assertInterviewNotHeld($interview)) {
            return $response;
        }

        $interview->update(['status' => 'No Show']);

        return response()->json(['ok' => true]);
    }

    public function complete(Request $request, HrmInterview $interview)
    {
        if ($response = $this->assertInterviewNotHeld($interview)) {
            return $response;
        }

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
     * Existing "Scheduled" interview occupying the same applicant/type/posting
     * slot — used so schedule/reschedule never leaves parallel duplicate rows.
     */
    private function findScheduledSlot(array $slot, ?int $exceptId = null): ?HrmInterview
    {
        return HrmInterview::query()
            ->where('applicant_id', $slot['applicant_id'] ?? null)
            ->where('job_posting_id', $slot['job_posting_id'] ?? null)
            ->where('type', $slot['type'] ?? null)
            ->where('date', $slot['date'] ?? null)
            ->where('start_time', $slot['start_time'] ?? null)
            ->where('status', 'Scheduled')
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->first();
    }

    /**
     * Which job posting an applicant's interview belongs to: latest
     * non-terminal job application first, then the applicant header link.
     */
    private function resolvePostingId(int $applicantId): ?int
    {
        return ApplicantJobApplication::where('applicant_id', $applicantId)
                ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
                ->notOnHold()
                ->latest()->value('job_posting_id')
            ?? ApplicantJobApplication::where('applicant_id', $applicantId)
                ->notOnHold()
                ->latest()->value('job_posting_id')
            ?? Applicant::whereKey($applicantId)->value('job_posting_id');
    }

    private function moveInterviewToOnboarding(HrmInterview $interview, $changedBy): HrmOnboarding
    {
        return DB::transaction(function () use ($interview, $changedBy) {
        $app = $interview->applicant;
        // Resolve the job when old interviews carry none, so each applied job
        // gets its own onboarding row instead of merging into the first one.
        $postingId = $interview->job_posting_id
            ?? ($interview->applicant_id ? $this->resolvePostingId((int) $interview->applicant_id) : null);

        if ($interview->applicant_id
            && ApplicantApplicationHoldService::isHeldFor((int) $interview->applicant_id, $postingId)) {
            abort(422, ApplicantApplicationHoldService::HOLD_MESSAGE);
        }

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
            $targetApplication = ApplicantJobApplication::where('applicant_id', $app->id)
                ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
                ->notOnHold()
                ->when($postingId, fn ($q) => $q->where('job_posting_id', $postingId))
                ->latest()
                ->first();

            if ($targetApplication) {
                // The model event closes the loop and holds this applicant's
                // other active applications automatically.
                $targetApplication->update(['status' => 'Onboarding']);
            }
            ApplicantStatusHistory::create([
                'applicant_id' => $app->id, 'from_status' => $from,
                'to_status' => 'Onboarding', 'changed_by' => $changedBy,
                'reason' => 'Advanced to onboarding #' . $onboarding->id,
            ]);
        }

        return $onboarding;
        });
    }

    public function select(Request $request, HrmInterview $interview)
    {
        if ($response = $this->assertInterviewNotHeld($interview)) {
            return $response;
        }

        $interview->update(['status' => 'Completed']);
        if ($app = $interview->applicant) {
            $from = $app->status;
            $app->update(['status' => 'Offered']);
            ApplicantJobApplication::where('applicant_id', $app->id)
                ->whereNotIn('status', ['Withdrawn', 'Rejected', 'Hired'])
                ->notOnHold()
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
