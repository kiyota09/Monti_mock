<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmPosition;
use App\Models\Hrm\HrmTraining;
use App\Models\Hrm\HrmTrainingEnrollment;
use App\Models\Hrm\HrmOnboarding;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\TraineeDailyObservation;
use App\Models\Hrm\TraineeGrade;
use App\Models\Hrm\TraineeTransfer;
use App\Models\Hrm\TraineeWeeklyEvaluation;
use App\Models\Core\User;
use App\Support\ReapplyCooldown;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Traits\HasPagePermissions;

class TraineeController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        $with = [
            'traineeGrade',
            'trainingEnrollments.training',
            'traineeTransfers',
            'hrmOrgPosition:id,name',
            'hrmDepartment:id,name',
            'hrmEmploymentType:id,name',
            'manager:id,name',
        ];

        // HRM now manages ALL trainees across the whole ERP
        // Fetch all users with position 'trainee', regardless of their role (module)
        $traineeUsers = User::where('position', 'trainee')
            ->with($with)
            ->orderBy('created_at', 'desc')
            ->get()
            // Exclude trainees already forwarded to HR / Onboarding
            ->filter(fn ($t) => ! ($t->traineeGrade?->passed_to_hr ?? false));

        // Newly created employees also land here so HR can monitor and
        // evaluate them: any rank hired in the last 30 days, newest first.
        // (Covers staff + manager/supervisor/secretary/special_officer/
        // vice_president created from Onboarding -> Create Employee.)
        $newStaff = User::whereIn('position', ['staff', 'supervisor', 'manager', 'secretary', 'special_officer', 'vice_president'])
            ->where('is_active', true)
            ->where('created_at', '>=', now()->subDays(30))
            ->with($with)
            ->orderByDesc('created_at')
            ->take(50)
            ->get();

        // A recent hire belongs in the training dashboard only when their
        // probationary job posting explicitly requires training. Resolve the
        // posting through the same onboarding/applicant links used at hire.
        $trainingCandidates = $traineeUsers->concat($newStaff);
        $newHireIds = $trainingCandidates->pluck('id');
        $newHireEmails = $trainingCandidates->flatMap(fn ($employee) => [$employee->email, $employee->personal_email])
            ->filter()->unique()->values();
        $applicantLinks = Applicant::query()
            ->where(function ($query) use ($newHireIds, $newHireEmails) {
                $query->whereIn('hired_user_id', $newHireIds)
                    ->orWhereIn('email', $newHireEmails);
            })
            ->with(['jobPosting:id,training_required', 'jobApplications.jobPosting:id,training_required'])
            ->orderByDesc('id')->get();
        $applicantsByUser = $applicantLinks->filter(fn ($applicant) => $applicant->hired_user_id)
            ->unique('hired_user_id')->keyBy('hired_user_id');
        $applicantsByEmail = $applicantLinks->unique('email')->keyBy('email');

        $onboardingLinks = HrmOnboarding::query()
            ->where(function ($query) use ($applicantLinks, $newHireEmails) {
                $query->whereIn('applicant_id', $applicantLinks->pluck('id'))
                    ->orWhereIn('email', $newHireEmails);
            })
            ->with('jobPosting:id,training_required')
            ->orderByDesc('id')->get();
        $onboardingsByApplicant = $onboardingLinks->filter(fn ($onboarding) => $onboarding->applicant_id)
            ->unique('applicant_id')->keyBy('applicant_id');
        $onboardingsByEmail = $onboardingLinks->unique('email')->keyBy('email');

        $requiresTraining = function ($employee) use ($applicantsByUser, $applicantsByEmail, $onboardingsByApplicant, $onboardingsByEmail) {
            if (strtolower((string) $employee->hrmEmploymentType?->name) !== 'probationary') {
                return true;
            }

            $applicant = $applicantsByUser[$employee->id] ?? $applicantsByEmail[$employee->personal_email ?: $employee->email] ?? null;
            $onboarding = ($applicant ? $onboardingsByApplicant[$applicant->id] ?? null : null)
                ?? $onboardingsByEmail[$employee->personal_email ?: $employee->email] ?? null;
            $posting = $onboarding?->jobPosting
                ?? $applicant?->jobPosting
                ?? $applicant?->jobApplications?->first()?->jobPosting;

            return (bool) $posting?->training_required;
        };
        $traineeUsers = $traineeUsers->filter($requiresTraining)->values();
        $newStaff = $newStaff->filter($requiresTraining)->values();

        $rows = $traineeUsers->concat($newStaff)
            ->sortByDesc('created_at')
            ->values();

        // Training list shows all training (no reporting-manager filtering).

        // Position they applied for (applicant record matched by email).
        $appliedMap = Applicant::whereIn('email', $rows->pluck('email')->filter()->unique()->all())
            ->orderBy('id')
            ->get()
            ->keyBy('email');

        $rows = $rows
            ->map(fn ($t) => self::traineeRow($t, $appliedMap[$t->email]->position_applied ?? null))
            ->values();

        // Page permissions are always under HRM module
        $permissions = $this->getPagePermissionsForModule('HRM');

        return Inertia::render('Dashboard/HRM_NEW/Trainee', [
            'trainees'      => $rows,
            'summary'       => self::summarizeRows($rows),
            'departments'   => HrmDepartment::whereNull('archived_at')->orderBy('name')->select('id', 'name')->get(),
            'positions'     => HrmPosition::whereNull('archived_at')->orderBy('name')->select('id', 'name', 'department_id')->get(),
            'roles'         => ['HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'PRO', 'PROJ', 'IT', 'LOG'],
            'currentModule' => 'HRM',   // fixed for HRM
            'permissions'   => $permissions,
            'evaluationWeights' => TraineeGrade::EVALUATION_WEIGHTS,
            'passingGrade' => TraineeGrade::PASSING_GRADE,
        ]);
    }

    /**
     * Pipeline summary for the Training page header, built from the same
     * rows shown in the table so the numbers always match the list.
     * Buckets are disjoint (priority: Passed > Failed > Evaluated >
     * Completed > In Progress > Scheduled) so they always sum to the total.
     *
     * Training Status -> Scheduled | In Progress | Completed | Extended | Cancelled
     * Evaluation      -> Not Ready | Ready for Evaluation | Evaluated
     * Result          -> Passed | Failed | Retraining |
     *                    Recommend for Regularization | Not Recommended
     *
     * Scheduled   -> employee exists, but training hasn't started
     *                (no enrollments, or still Enrolled)
     * In Progress -> training has started (Extended counts here: still training)
     * Completed   -> all trainings done, awaiting evaluation
     * Evaluated   -> HR has saved the weighted evaluation
     *                (recommendations awaiting HR approval count here,
     *                not as Passed — only confirmed passes do)
     * Passed      -> confirmed complete / promoted to staff
     * Failed      -> archived (Cancelled counts here)
     */
    protected static function summarizeRows($rows): array
    {
        $summary = [
            'scheduled' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'evaluated' => 0,
            'passed' => 0,
            'failed' => 0,
        ];

        foreach ($rows as $row) {
            $row = is_array($row) ? $row : (array) $row;
            $res = $row['result'] ?? null;
            $evalState = $row['evaluation_state'] ?? $row['evaluation_status'] ?? null;
            $training = $row['training_status'] ?? null;
            if ($res === 'Passed') {
                $summary['passed']++;
            } elseif (in_array($res, ['Failed', 'Not Recommended'], true) || $training === 'Cancelled') {
                $summary['failed']++;
            } elseif (in_array($evalState, ['Evaluated'], true)) {
                $summary['evaluated']++;
            } elseif ($training === 'Completed') {
                $summary['completed']++;
            } elseif (in_array($training, ['In Progress', 'Extended'], true)) {
                $summary['in_progress']++;
            } else {
                $summary['scheduled']++;
            }
        }

        $summary['total'] = array_sum($summary);

        return $summary;
    }

    /**
     * Shape one trainee row for the Training page table.
     * Columns: Trainee | Employee ID | Position | Department | Start Date |
     *          Training Status | Grade | Evaluation | Action
     */
    public static function traineeRow(User $t, ?string $appliedPosition = null): array
    {
        $grade = $t->traineeGrade;
        $hasScores = $grade && ($grade->final_grade !== null
            || in_array($grade->evaluation_status, ['Evaluated', 'Passed', 'Failed'], true));
        $finalGrade = $grade?->final_grade !== null
            ? (float) $grade->final_grade
            : ($hasScores ? (float) ($grade->total_percentage ?? 0) : null);

        $trainings = ($t->trainingEnrollments ?? collect())->map(fn ($e) => [
            'id' => $e->training?->id ?? $e->training_id,
            'name' => $e->training?->name ?? 'Training #' . $e->training_id,
            'status' => $e->status, // Enrolled | In Progress | Completed
            'progress' => (int) ($e->progress ?? 0),
        ])->values()->all();

        $transferHistory = ($t->traineeTransfers ?? collect())->map(fn ($tr) => [
            'id' => $tr->id,
            'from_department' => $tr->from_department,
            'from_position' => $tr->from_position,
            'to_department' => $tr->to_department,
            'to_position' => $tr->to_position,
            'effective_date' => $tr->effective_date?->format('Y-m-d'),
            'reason' => $tr->reason,
            'new_training_required' => (bool) $tr->new_training_required,
            'training_result' => $tr->training_result,
            'previous_grade' => $tr->previous_grade !== null ? (float) $tr->previous_grade : null,
        ])->values()->all();
        $latestTransfer = collect($transferHistory)->last();

        $evaluationStatus = self::resolveEvaluationStatus($t);
        $trainingStatus = self::resolveTrainingStatus($t);

        $retraining = (bool) ($grade?->retraining ?? false);
        $retrainingCount = (int) ($grade?->retraining_count ?? 0);
        $passedToHr = (bool) ($grade?->passed_to_hr ?? false);
        $recommendation = $grade?->manager_recommendation;
        // Fresh retraining cycle (scores cleared, trainings re-enrolled):
        // Scheduled until the retraining start date arrives and the cycle
        // actually restarts.
        $freshRetrain = $retraining && $evaluationStatus === 'Pending' && $retrainingCount > 0;
        $cycleRestarted = collect($trainings)->contains(
            fn ($tr) => in_array($tr['status'] ?? null, ['In Progress', 'Completed'], true)
        );

        // --- Separated display concepts (Training List page) ---
        // Training Status: Scheduled | In Progress | Completed | Extended | Cancelled
        if (! $t->is_active) {
            $trainingStatus = 'Cancelled';
        } elseif ($passedToHr || $evaluationStatus === 'Passed') {
            $trainingStatus = 'Completed';
        } elseif ($retraining && $finalGrade !== null) {
            // Scores kept but sent back for more training (e.g. Extend Training).
            $trainingStatus = 'Extended';
        } elseif ($evaluationStatus === 'Evaluated') {
            // Once HR has evaluated the trainee, training is done —
            // display Completed regardless of enrollment rows.
            $trainingStatus = 'Completed';
        } elseif ($freshRetrain && ! $cycleRestarted && ! self::dateArrived($grade?->retraining_start_date)) {
            $trainingStatus = 'Scheduled';
        }

        // Evaluation: Not Ready | Ready for Evaluation | Evaluated.
        // Whether the final decision is made lives in HR Review
        // (hr_review_status: Pending | Approved | Extended | Rejected).
        if ($finalGrade !== null) {
            $evaluationState = 'Evaluated';
        } elseif (! $t->is_active) {
            $evaluationState = 'Not Ready';
        } elseif ($freshRetrain && ! $cycleRestarted) {
            $evaluationState = 'Not Ready';
        } else {
            $trainingComplete = collect($trainings)->isNotEmpty()
                && collect($trainings)->every(fn ($tr) => ($tr['status'] ?? null) === 'Completed');
            $endDate = self::resolveTrainingEndDate($t);
            try {
                $endArrived = $endDate && \Carbon\Carbon::parse($endDate)->toDateString() <= now()->toDateString();
            } catch (\Throwable $e) {
                $endArrived = false;
            }
            $evaluationState = ($trainingComplete || $endArrived) ? 'Ready for Evaluation' : 'Not Ready';
        }

        // HR review & decision (null until HR acts; defaults to Pending
        // once a final evaluation exists).
        $hrReview = [
            'status' => $grade?->hr_review_status ?? ($finalGrade !== null ? 'Pending' : null),
            'reviewer_id' => $grade?->hr_reviewer_id,
            'notes' => $grade?->hr_review_notes,
            'reviewed_at' => $grade?->hr_reviewed_at?->toDateTimeString(),
            'regularization_date' => $t->regularization_date ?? null,
        ];

        // Result: Passed | Failed | Retraining | Recommend for Regularization |
        //         Not Recommended | null (not yet graded)
        if ($freshRetrain) {
            $result = 'Retraining';
        } elseif ($finalGrade === null) {
            $result = null;
        } elseif ($passedToHr) {
            $result = 'Passed';
        } elseif (($grade?->hr_review_status ?? null) === 'Rejected') {
            $result = 'Not Recommended';
        } elseif (($grade?->hr_review_status ?? null) === 'Extended') {
            $result = 'Retraining';
        } elseif ($recommendation === 'regularization') {
            $result = 'Recommend for Regularization';
        } elseif ($recommendation === 'needs_improvement') {
            $result = 'Needs Improvement';
        } elseif ($recommendation === 'reassigned') {
            $result = 'Reassigned';
        } elseif ($recommendation === 'extend') {
            $result = 'Retraining';
        } elseif ($recommendation === 'do_not_recommend') {
            $result = 'Not Recommended';
        } else {
            $result = $finalGrade >= TraineeGrade::PASSING_GRADE ? 'Passed' : 'Failed';
        }

        return [
            'id' => $t->id,
            'name' => $t->name,
            'email' => $t->email,
            'employee_id' => $t->employee_id ?? ('TRN-' . str_pad((string) $t->id, 4, '0', STR_PAD_LEFT)),
            'position' => $latestTransfer['to_position'] ?? $appliedPosition ?? $t->hrmOrgPosition?->name ?? ucfirst((string) $t->position),
            'is_trainee' => $t->position === 'trainee',
            'department' => $latestTransfer['to_department'] ?? $t->hrmDepartment?->name ?? $t->department ?? $t->role,
            'role' => $t->role, // kept for BC (original module)
            'start_date' => $t->join_date ?? optional($t->created_at)->toDateString(),
            'join_date' => $t->join_date, // BC
            'is_active' => (bool) $t->is_active,
            'employment_status' => $t->employment_status ?? ($t->position === 'trainee' ? 'Trainee' : ucfirst((string) $t->position)),
            'training_status' => $trainingStatus,
            'trainings' => $trainings,
            'grade' => $finalGrade,
            'grade_percentage' => $finalGrade ?? 0, // BC
            'evaluation_status' => $evaluationStatus,
            'evaluation_state' => $evaluationState,
            'result' => $result,
            'hr_review' => $hrReview,
            'retraining' => $retraining,
            'retraining_count' => $retrainingCount,
            'retraining_start_date' => $grade?->retraining_start_date?->format('Y-m-d'),
            'retraining_end_date' => $grade?->retraining_end_date?->format('Y-m-d'),
            'retraining_reason' => $grade?->retraining_reason,
            'retraining_notes' => $grade?->retraining_notes,
            'transfers' => $transferHistory,
            'trainee_grade' => $grade, // BC
            'manager_id' => $t->manager_id,
            'manager_name' => $t->manager?->name ?? $t->immediate_supervisor ?? $t->department_head,
            'reporting_manager' => $t->manager?->name ?? $t->immediate_supervisor ?? $t->department_head,
            // Training schedule connected to Create Employee data
            // (users.training_start_date / training_end_date /
            // training_duration_weeks). Falls back to join_date + 8 weeks
            // for hires created before the schedule fields existed.
            'training_start_date' => $t->training_start_date ?? $t->join_date ?? optional($t->created_at)->toDateString(),
            'training_end_date' => self::resolveTrainingEndDate($t),
            'training_duration_weeks' => (int) ($t->training_duration_weeks ?? 8),
            'evaluation' => $grade ? [
                'attendance' => $grade->attendance !== null ? (float) $grade->attendance : null,
                'knowledge' => $grade->knowledge !== null ? (float) $grade->knowledge : null,
                'practical_skills' => $grade->practical_skills !== null ? (float) $grade->practical_skills : null,
                'work_behavior' => $grade->work_behavior !== null ? (float) $grade->work_behavior : null,
                'safety_compliance' => $grade->safety_compliance !== null ? (float) $grade->safety_compliance : null,
                'final_grade' => $grade->final_grade !== null ? (float) $grade->final_grade : null,
                'remarks' => $grade->remarks,
                'status' => $grade->evaluation_status ?? 'Pending',
                'evaluated_at' => $grade->evaluated_at,
            ] : null,
        ];
    }

    protected static function resolveTrainingStatus(User $t): string
    {
        // Only three display states: Scheduled | In Progress | Completed.
        // "Scheduled" = employee exists but training hasn't started
        // (start date still in the future).
        $enrollments = $t->trainingEnrollments ?? collect();
        if ($enrollments->isNotEmpty() && $enrollments->every(fn ($e) => $e->status === 'Completed')) {
            return 'Completed';
        }
        // Once the employee start date arrives, training is underway.
        if (self::hasStartDateArrived($t)) {
            return 'In Progress';
        }
        if ($enrollments->isNotEmpty() && $enrollments->contains(fn ($e) => in_array($e->status, ['In Progress', 'Completed'], true))) {
            return 'In Progress';
        }

        return 'Scheduled';
    }

    protected static function hasStartDateArrived(User $t): bool
    {
        return self::dateArrived($t->training_start_date ?? $t->join_date);
    }

    protected static function resolveTrainingEndDate(User $t): ?string
    {
        if (! empty($t->training_end_date)) {
            try {
                return \Carbon\Carbon::parse($t->training_end_date)->toDateString();
            } catch (\Throwable $e) {
                // fall through to derived date
            }
        }
        $weeks = (int) ($t->training_duration_weeks ?? 8);
        $weeks = max(1, min(52, $weeks ?: 8));
        $anchor = $t->training_start_date ?? $t->join_date ?? optional($t->created_at)->toDateString();
        if (empty($anchor)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($anchor)->addWeeks($weeks)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected static function dateArrived($date): bool
    {
        if (empty($date)) {
            return false;
        }
        try {
            return \Carbon\Carbon::parse($date)->toDateString() <= now()->toDateString();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The position the person applied for (latest applicant record by email),
     * or null when they have no applicant record.
     */
    protected static function appliedPositionFor(User $t): ?string
    {
        if (! $t->email) {
            return null;
        }

        return Applicant::where('email', $t->email)
            ->orderByDesc('id')
            ->value('position_applied');
    }

    /**
     * Pending   -> no evaluation saved yet (or reset for retraining)
     * Evaluated -> scores saved, grade computed, awaiting HR decision
     * Passed    -> confirmed complete / promoted to staff (passed_to_hr)
     * Failed    -> failing grade (stays active until retrained), or
     *              archived (is_active = false)
     */
    protected static function resolveEvaluationStatus(User $t): string
    {
        if (! $t->is_active) {
            return 'Failed';
        }
        $grade = $t->traineeGrade;
        if ($grade?->passed_to_hr) {
            return 'Passed';
        }
        if ($grade && ($grade->evaluation_status === 'Passed' || $grade->evaluation_status === 'Failed')) {
            return $grade->evaluation_status;
        }
        if ($grade && ($grade->final_grade !== null || $grade->evaluation_status === 'Evaluated')) {
            return 'Evaluated';
        }

        return 'Pending';
    }

    /**
     * Full-view accounts: CEO role and higher-ups
     * (secretary / special_officer / vice_president) only.
     * HRM included? No — strict assigned-only: even other HR with
     * manager/supervisor rank cannot see rows assigned to someone else.
     * HRM only keeps the unassigned pool (manager_id null).
     */
    protected static function isFullTrainingViewer($viewer): bool
    {
        if (! $viewer) {
            return false;
        }
        if (strtoupper((string) ($viewer->role ?? '')) === 'CEO') {
            return true;
        }
        if (in_array(strtolower((string) ($viewer->position ?? '')), ['secretary', 'special_officer', 'vice_president'], true)) {
            return true;
        }

        return false;
    }

    protected static function authorizeTraineeAccess($viewer, User $trainee): void
    {
        // Training list shows all training — page.permission middleware
        // already gates access, no per-row reporting-manager restriction here.
        // (Strict assigned-only lives in My Assigned Trainees page.)
        return;
    }

    /** Public proxy so AssignedTraineesController reuses the same access rule. */
    public static function authorizeTraineeAccessProxy($viewer, User $trainee): void
    {
        self::authorizeTraineeAccess($viewer, $trainee);
    }

    public function show(Request $request, $id)
    {
        $trainee = User::whereIn('position', ['trainee', 'staff', 'supervisor', 'manager', 'secretary', 'special_officer', 'vice_president'])
            ->with(['traineeGrade', 'trainingEnrollments.training', 'traineeTransfers', 'hrmOrgPosition:id,name', 'hrmDepartment:id,name', 'manager:id,name', 'weeklyEvaluations' => fn ($q) => $q->orderBy('week_number')])
            ->findOrFail($id);
        self::authorizeTraineeAccess($request->user(), $trainee);

        // Full training detail (weekly evaluations, attendance logs,
        // daily observations, schedule, final scores) for the read-only
        // Trainee Training Details view. Same shape as My Assigned
        // Trainees rows (includes the base training-list fields).
        $row = AssignedTraineesController::assignedRow($trainee, self::appliedPositionFor($trainee));

        // Display names (single-row lookups only — never in list builders).
        $grade = $trainee->traineeGrade;
        if (! empty($row['hr_review']['reviewer_id'])) {
            $row['hr_review']['reviewer_name'] = User::where('id', $row['hr_review']['reviewer_id'])->value('name');
        }
        $row['final_evaluator_name'] = $grade?->evaluated_by
            ? User::where('id', $grade->evaluated_by)->value('name')
            : null;
        $row['final_evaluated_at'] = $grade?->evaluated_at?->toDateTimeString();

        return response()->json([
            'trainee' => $row,
            'weights' => TraineeGrade::EVALUATION_WEIGHTS,
            'passing_grade' => TraineeGrade::PASSING_GRADE,
            // Eligible reporting managers for the Approve Reassignment form.
            'managers' => User::where('is_active', true)
                ->whereIn('position', ['manager', 'supervisor', 'secretary', 'special_officer', 'vice_president'])
                ->orderBy('name')->select('id', 'name', 'position', 'department')->take(200)->get(),
        ]);
    }

    /**
     * HR review & decision on a submitted final evaluation.
     * HR Staff = reviewer and decision-maker (manager = evaluator/recommender).
     *
     * approve_regularization -> Trainee becomes Regular (passed_to_hr,
     *    evaluation Passed, employment_status Regular, regularization
     *    effective date). Row leaves the active trainee list; history kept.
     * extend_training       -> back to training (retraining cycle +1).
     * do_not_regularize     -> end employment (inactive, Failed).
     * approve_reassignment  -> transfer to a new department/position/manager
     *    (only when the manager recommended Reassigned) + fresh training.
     * reject_reassignment   -> reassignment declined, end employment.
     */
    public function hrReview(Request $request, $id)
    {
        $validated = $request->validate([
            'decision' => 'required|in:approve_regularization,do_not_regularize,approve_reassignment,reject_reassignment',
            'notes' => 'nullable|string|max:2000|required_if:decision,do_not_regularize,approve_reassignment,reject_reassignment',
            'effective_date' => 'nullable|date|required_if:decision,approve_regularization,approve_reassignment',
            'to_department_id' => 'nullable|exists:hrm_departments,id|required_if:decision,approve_reassignment',
            'to_position' => 'nullable|string|max:255|required_if:decision,approve_reassignment',
            'manager_id' => 'nullable|exists:users,id|required_if:decision,approve_reassignment',
            'role' => 'nullable|string|in:HRM,SCM,FIN,MAN,INV,ORD,WAR,CRM,ECO,PRO,PROJ,IT,LOG',
        ]);

        $trainee = User::whereIn('position', ['trainee', 'staff', 'supervisor', 'manager', 'secretary', 'special_officer', 'vice_president'])->findOrFail($id);
        self::authorizeTraineeAccess($request->user(), $trainee);

        $grade = $trainee->traineeGrade;
        if (! $grade || $grade->final_grade === null) {
            $msg = 'No final evaluation submitted yet for ' . $trainee->name . '.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $msg], 422);
            }

            return back()->withErrors(['hr_review' => $msg]);
        }
        if (in_array($grade->hr_review_status, ['Approved', 'Extended', 'Rejected', 'Reassigned'], true)) {
            $msg = 'HR review already recorded for ' . $trainee->name . '.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $msg], 422);
            }

            return back()->withErrors(['hr_review' => $msg]);
        }

        // Reassignment decisions are only valid when the manager recommended
        // Reassigned; standard decisions only otherwise.
        $isReassignment = ($grade->manager_recommendation ?? null) === 'reassigned';
        $reassignDecisions = ['approve_reassignment', 'reject_reassignment'];
        if ($isReassignment && ! in_array($validated['decision'], $reassignDecisions, true)) {
            $msg = 'The manager recommended Reassignment — choose Approve or Reject Reassignment.';

            return $request->wantsJson()
                ? response()->json(['message' => $msg], 422)
                : back()->withErrors(['hr_review' => $msg]);
        }
        if (! $isReassignment && in_array($validated['decision'], $reassignDecisions, true)) {
            $msg = 'Reassignment review is only available when the manager recommended Reassignment.';

            return $request->wantsJson()
                ? response()->json(['message' => $msg], 422)
                : back()->withErrors(['hr_review' => $msg]);
        }

        $review = [
            'hr_reviewer_id' => $request->user()?->id,
            'hr_review_notes' => $validated['notes'] ?? null,
            'hr_reviewed_at' => now(),
        ];

        if ($validated['decision'] === 'approve_regularization') {
            if ((float) $grade->final_grade < TraineeGrade::PASSING_GRADE) {
                $msg = $trainee->name . ' is below the passing grade (' . TraineeGrade::PASSING_GRADE . '%) and cannot be regularized.';
                if ($request->wantsJson()) {
                    return response()->json(['message' => $msg], 422);
                }

                return back()->withErrors(['hr_review' => $msg]);
            }
            $grade->update(array_merge($review, [
                'hr_review_status' => 'Approved',
                'evaluation_status' => 'Passed',
                'passed_to_hr' => true,
            ]));
            $trainee->update([
                'is_active' => true,
                'employment_status' => 'Regular',
                'regularization_date' => $validated['effective_date'],
            ]);
            $msg = $trainee->name . ' approved for regularization (Regular effective ' . \Carbon\Carbon::parse($validated['effective_date'])->toDateString() . ').';
        } elseif ($validated['decision'] === 'extend_training') {
            $grade->update(array_merge($review, [
                'hr_review_status' => 'Extended',
                'evaluation_status' => 'Evaluated',
                'passed_to_hr' => false,
                'retraining' => true,
                'retraining_count' => ((int) $grade->retraining_count) + 1,
            ]));
            $msg = 'Training extended for ' . $trainee->name . ' (retraining cycle ' . ((int) $grade->retraining_count) . ').';
        } elseif ($validated['decision'] === 'approve_reassignment') {
            if ((int) $validated['manager_id'] === (int) $trainee->id) {
                $msg = 'The new reporting manager cannot be the trainee themselves.';

                return $request->wantsJson()
                    ? response()->json(['message' => $msg], 422)
                    : back()->withErrors(['hr_review' => $msg]);
            }
            $dept = HrmDepartment::findOrFail($validated['to_department_id']);
            $positionModel = HrmPosition::where('name', $validated['to_position'])
                ->where(fn ($q) => $q->where('department_id', $dept->id)->orWhereNull('department_id'))
                ->first();
            $newManager = User::where('is_active', true)->findOrFail($validated['manager_id']);
            $applied = self::appliedPositionFor($trainee);
            $history = $trainee->traineeTransfers ?? collect();
            $latest = $history->last();

            TraineeTransfer::create([
                'user_id' => $trainee->id,
                'from_department' => $latest?->to_department ?? $trainee->hrmDepartment?->name ?? $trainee->department ?? $trainee->role,
                'from_position' => $latest?->to_position ?? $applied ?? $trainee->hrmOrgPosition?->name ?? ucfirst((string) $trainee->position),
                'to_department' => $dept->name,
                'to_department_id' => $dept->id,
                'to_position' => $validated['to_position'],
                'to_position_id' => $positionModel?->id,
                'effective_date' => $validated['effective_date'],
                'reason' => $validated['notes'],
                'new_training_required' => true,
                'training_result' => self::resolveEvaluationStatus($trainee),
                'previous_grade' => $grade?->final_grade ?? $grade?->total_percentage,
                'transferred_by' => $request->user()->id,
            ]);

            // Move the live record to the new assignment + reporting manager.
            // Explicit role from the form wins; derivation is only a fallback.
            $role = $validated['role'] ?? strtoupper($dept->name);
            $validRoles = ['HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'PRO', 'PROJ', 'IT', 'LOG'];
            if (! in_array($role, $validRoles, true)) {
                $role = ($dept->category && in_array(strtoupper((string) $dept->category), $validRoles, true))
                    ? strtoupper((string) $dept->category) : $trainee->role;
            }
            $trainee->update([
                'role' => $role,
                'department' => $dept->name,
                'hrm_department_id' => $dept->id,
                'hrm_position_id' => $positionModel?->id ?? $trainee->hrm_position_id,
                'manager_id' => $newManager->id,
            ]);

            // Fresh training cycle for the new position (mirrors transfer):
            // scores cleared, re-enrolled, HR review recorded as Reassigned.
            $grade->update(array_merge($review, [
                'hr_review_status' => 'Reassigned',
                'attendance' => null,
                'knowledge' => null,
                'practical_skills' => null,
                'work_behavior' => null,
                'safety_compliance' => null,
                'final_grade' => null,
                'total_percentage' => 0,
                'evaluation_status' => 'Pending',
                'remarks' => null,
                'evaluated_at' => null,
                'evaluated_by' => null,
            ]));
            HrmTrainingEnrollment::where('user_id', $trainee->id)
                ->update(['status' => 'Enrolled', 'progress' => 0, 'completed_at' => null]);
            // Fresh weekly cycle: old week records belong to the previous
            // assignment's calendar — delete them so W1/W2 restart from the
            // reassignment effective date.
            TraineeWeeklyEvaluation::where('user_id', $trainee->id)->delete();
            AttendanceLog::where('user_id', $trainee->id)->delete();
            TraineeDailyObservation::where('user_id', $trainee->id)->delete();
            $msg = $trainee->name . ' reassigned to ' . $validated['to_position'] . ' — ' . $dept->name . ' (reporting to ' . $newManager->name . ') effective ' . \Carbon\Carbon::parse($validated['effective_date'])->toDateString() . '.';
        } elseif ($validated['decision'] === 'reject_reassignment') {
            $grade->update(array_merge($review, [
                'hr_review_status' => 'Rejected',
                'evaluation_status' => 'Failed',
                'passed_to_hr' => false,
            ]));
            // Training failure, not termination: company access refused but
            // the applicant portal stays usable under the 6-month same-job
            // cooldown (other jobs remain open).
            ReapplyCooldown::recordTrainingFailure($trainee, 'Reassignment rejected — failed training');
            $msg = 'Reassignment rejected for ' . $trainee->name . '. Employment ended.';
        } else {
            $grade->update(array_merge($review, [
                'hr_review_status' => 'Rejected',
                'evaluation_status' => 'Failed',
                'passed_to_hr' => false,
            ]));
            ReapplyCooldown::recordTrainingFailure($trainee, 'Not regularized — failed training');
            $msg = $trainee->name . ' was not regularized. Employment ended.';
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => $msg]);
        }

        return back()->with('message', $msg);
    }

    /**
     * Save the weighted training evaluation.
     * Final grade = Attendance*20% + Knowledge*25% + Practical*30% +
     *               Behavior*15% + Safety*10% (auto-calculated).
     */
    public function evaluate(Request $request, $id)
    {
        $validated = $request->validate([
            'attendance' => 'required|numeric|min:0|max:100',
            'knowledge' => 'required|numeric|min:0|max:100',
            'practical_skills' => 'required|numeric|min:0|max:100',
            'work_behavior' => 'required|numeric|min:0|max:100',
            'safety_compliance' => 'required|numeric|min:0|max:100',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $trainee = User::whereIn('position', ['trainee', 'staff', 'supervisor', 'manager', 'secretary', 'special_officer', 'vice_president'])->findOrFail($id);
        self::authorizeTraineeAccess($request->user(), $trainee);

        $finalGrade = TraineeGrade::computeFinalGrade($validated);
        $result = $finalGrade >= TraineeGrade::PASSING_GRADE ? 'Passed' : 'Failed';

        $grade = TraineeGrade::updateOrCreate(
            ['user_id' => $trainee->id],
            [
                'attendance' => $validated['attendance'],
                'knowledge' => $validated['knowledge'],
                'practical_skills' => $validated['practical_skills'],
                'work_behavior' => $validated['work_behavior'],
                'safety_compliance' => $validated['safety_compliance'],
                'final_grade' => $finalGrade,
                'total_percentage' => $finalGrade, // BC with old grade display
                'evaluation_status' => $result === 'Passed' ? 'Evaluated' : 'Failed',
                'remarks' => $validated['remarks'] ?? null,
                'evaluated_at' => now(),
                'evaluated_by' => $request->user()->id,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Evaluation saved.',
                'final_grade' => $finalGrade,
                'result' => $result,
                'trainee' => self::traineeRow($trainee->fresh(['traineeGrade', 'trainingEnrollments.training', 'traineeTransfers', 'hrmOrgPosition', 'hrmDepartment']), self::appliedPositionFor($trainee)),
            ]);
        }

        return back()->with('message', "Evaluation saved for {$trainee->name}. Final grade: {$finalGrade}% ({$result}).");
    }

    /**
     * Transfer the employee to another position/department after a failed
     * evaluation. History is preserved in trainee_transfers (never
     * overwritten); the live record moves to the new assignment so a fresh
     * training/evaluation cycle can start there.
     */
    public function transfer(Request $request, $id)
    {
        $validated = $request->validate([
            'to_department_id' => 'required|exists:hrm_departments,id',
            'to_position' => 'required|string|max:255',
            'effective_date' => 'required|date',
            'reason' => 'required|string|max:2000',
            'new_training_required' => 'nullable|boolean',
            'role' => 'nullable|string|in:HRM,SCM,FIN,MAN,INV,ORD,WAR,CRM,ECO,PRO,PROJ,IT,LOG',
        ]);
        $newTraining = (bool) ($validated['new_training_required'] ?? true);

        $trainee = User::whereIn('position', ['trainee', 'staff', 'supervisor', 'manager', 'secretary', 'special_officer', 'vice_president'])->findOrFail($id);
        self::authorizeTraineeAccess($request->user(), $trainee);
        $grade = $trainee->traineeGrade;
        $applied = self::appliedPositionFor($trainee);
        $history = $trainee->traineeTransfers ?? collect();
        $latest = $history->last();

        $dept = HrmDepartment::findOrFail($validated['to_department_id']);
        $positionModel = HrmPosition::where('name', $validated['to_position'])
            ->where(fn ($q) => $q->where('department_id', $dept->id)->orWhereNull('department_id'))
            ->first();

        $transfer = TraineeTransfer::create([
            'user_id' => $trainee->id,
            'from_department' => $latest?->to_department ?? $trainee->hrmDepartment?->name ?? $trainee->department ?? $trainee->role,
            'from_position' => $latest?->to_position ?? $applied ?? $trainee->hrmOrgPosition?->name ?? ucfirst((string) $trainee->position),
            'to_department' => $dept->name,
            'to_department_id' => $dept->id,
            'to_position' => $validated['to_position'],
            'to_position_id' => $positionModel?->id,
            'effective_date' => $validated['effective_date'],
            'reason' => $validated['reason'],
            'new_training_required' => $newTraining,
            'training_result' => self::resolveEvaluationStatus($trainee),
            'previous_grade' => $grade?->final_grade ?? $grade?->total_percentage,
            'transferred_by' => $request->user()->id,
        ]);

        // Move the live record to the new assignment.
        // Explicit role from the form wins; derivation is only a fallback.
        $role = $validated['role'] ?? strtoupper($dept->name);
        $validRoles = ['HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'PRO', 'PROJ', 'IT', 'LOG'];
        if (! in_array($role, $validRoles, true)) {
            $role = ($dept->category && in_array(strtoupper((string) $dept->category), $validRoles, true))
                ? strtoupper((string) $dept->category) : $trainee->role;
        }
        $trainee->update([
            'role' => $role,
            'department' => $dept->name,
            'hrm_department_id' => $dept->id,
            'hrm_position_id' => $positionModel?->id ?? $trainee->hrm_position_id,
        ]);

        // New training cycle for the new position: reset scores to Pending
        // and re-enroll (previous result stays in the transfer history).
        if ($newTraining && $grade) {
            $grade->update([
                'attendance' => null,
                'knowledge' => null,
                'practical_skills' => null,
                'work_behavior' => null,
                'safety_compliance' => null,
                'final_grade' => null,
                'total_percentage' => 0,
                'evaluation_status' => 'Pending',
                'remarks' => null,
                'evaluated_at' => null,
                'evaluated_by' => null,
            ]);
            HrmTrainingEnrollment::where('user_id', $trainee->id)
                ->update(['status' => 'Enrolled', 'progress' => 0, 'completed_at' => null]);
            // Fresh weekly cycle: old week records belong to the previous
            // assignment's calendar — delete them so W1/W2 restart from the
            // transfer effective date.
            TraineeWeeklyEvaluation::where('user_id', $trainee->id)->delete();
            AttendanceLog::where('user_id', $trainee->id)->delete();
            TraineeDailyObservation::where('user_id', $trainee->id)->delete();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Employee transferred.',
                'transfer' => $transfer,
                'trainee' => self::traineeRow(
                    $trainee->fresh(['traineeGrade', 'trainingEnrollments.training', 'traineeTransfers', 'hrmOrgPosition', 'hrmDepartment']),
                    $applied
                ),
            ]);
        }

        return back()->with('message', "{$trainee->name} transferred to {$validated['to_position']} — {$dept->name} effective " . \Carbon\Carbon::parse($validated['effective_date'])->format('F j, Y') . '.');
    }

    /**
     * Schedule retraining for a failed evaluation.
     * Accepts fresh scores (when called straight from the Evaluate modal)
     * or reuses the saved failing evaluation. Resets the record to a new
     * cycle: Training Status Scheduled, Evaluation Pending,
     * Retraining Yes + count incremented.
     */
    public function retrain(Request $request, $id)
    {
        $trainee = User::whereIn('position', ['trainee', 'staff', 'supervisor', 'manager', 'secretary', 'special_officer', 'vice_president'])->findOrFail($id);
        self::authorizeTraineeAccess($request->user(), $trainee);
        $grade = $trainee->traineeGrade;

        $scoreKeys = ['attendance', 'knowledge', 'practical_skills', 'work_behavior', 'safety_compliance'];
        if (collect($scoreKeys)->contains(fn ($k) => $request->filled($k))) {
            $validated = $request->validate([
                'attendance' => 'required|numeric|min:0|max:100',
                'knowledge' => 'required|numeric|min:0|max:100',
                'practical_skills' => 'required|numeric|min:0|max:100',
                'work_behavior' => 'required|numeric|min:0|max:100',
                'safety_compliance' => 'required|numeric|min:0|max:100',
                'remarks' => 'nullable|string|max:2000',
                'retraining_start_date' => 'required|date',
                'retraining_end_date' => 'nullable|date|after_or_equal:retraining_start_date',
                'retraining_reason' => 'required|string|max:2000',
                'retraining_notes' => 'nullable|string|max:2000',
            ]);

            $finalGrade = TraineeGrade::computeFinalGrade($validated);
            $grade = TraineeGrade::updateOrCreate(
                ['user_id' => $trainee->id],
                [
                    'attendance' => $validated['attendance'],
                    'knowledge' => $validated['knowledge'],
                    'practical_skills' => $validated['practical_skills'],
                    'work_behavior' => $validated['work_behavior'],
                    'safety_compliance' => $validated['safety_compliance'],
                    'final_grade' => $finalGrade,
                    'total_percentage' => $finalGrade,
                    'evaluation_status' => $finalGrade >= TraineeGrade::PASSING_GRADE ? 'Evaluated' : 'Failed',
                    'remarks' => $validated['remarks'] ?? $grade?->remarks,
                    'evaluated_at' => now(),
                    'evaluated_by' => $request->user()->id,
                ]
            );
        } else {
            $request->validate([
                'retraining_start_date' => 'required|date',
                'retraining_end_date' => 'nullable|date|after_or_equal:retraining_start_date',
                'retraining_reason' => 'required|string|max:2000',
                'retraining_notes' => 'nullable|string|max:2000',
            ]);
        }

        if (! $grade) {
            return back()->with('error', 'Evaluate this employee before scheduling retraining.');
        }

        $effective = (float) ($grade->final_grade ?? $grade->total_percentage ?? 0);
        if ($effective >= TraineeGrade::PASSING_GRADE) {
            return back()->with('message', "{$trainee->name} already meets the passing grade — no retraining needed.");
        }

        $cycle = ((int) $grade->retraining_count) + 1;
        $grade->update([
            'attendance' => null,
            'knowledge' => null,
            'practical_skills' => null,
            'work_behavior' => null,
            'safety_compliance' => null,
            'final_grade' => null,
            'total_percentage' => 0,
            'evaluation_status' => 'Pending',
            'remarks' => null,
            'evaluated_at' => null,
            'evaluated_by' => null,
            'retraining' => true,
            'retraining_count' => $cycle,
            'retraining_start_date' => $request->input('retraining_start_date'),
            'retraining_end_date' => $request->input('retraining_end_date'),
            'retraining_reason' => $request->input('retraining_reason'),
            'retraining_notes' => $request->input('retraining_notes'),
        ]);
        HrmTrainingEnrollment::where('user_id', $trainee->id)
            ->update(['status' => 'Enrolled', 'progress' => 0, 'completed_at' => null]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Retraining scheduled.',
                'retraining_count' => $cycle,
                'trainee' => self::traineeRow(
                    $trainee->fresh(['traineeGrade', 'trainingEnrollments.training', 'traineeTransfers', 'hrmOrgPosition', 'hrmDepartment']),
                    self::appliedPositionFor($trainee)
                ),
            ]);
        }

        return back()->with('message', "Retraining scheduled for {$trainee->name} (cycle {$cycle}). They are now Scheduled with evaluation Pending.");
    }

    public function grade(Request $request, $id)
    {
        $trainee = User::findOrFail($id);
        self::authorizeTraineeAccess($request->user(), $trainee);
        $validated = $request->validate([
            'skills_performance' => 'required|integer|min:1|max:5',
            'behaviour'          => 'required|integer|min:1|max:5',
            'technicals'         => 'required|integer|min:1|max:5',
            'safety_awareness'   => 'required|integer|min:1|max:5',
            'productivity'       => 'required|integer|min:1|max:5',
        ]);

        $total      = array_sum($validated);
        $percentage = ($total / 25) * 100;

        TraineeGrade::updateOrCreate(
            ['user_id' => $id],
            array_merge($validated, ['total_percentage' => $percentage])
        );

        return back()->with('message', 'Grade successfully saved.');
    }

    public function pass(Request $request, $id)
    {
        $trainee = User::findOrFail($id);
        self::authorizeTraineeAccess($request->user(), $trainee);

        if (! in_array($trainee->position, ['trainee', 'staff', 'supervisor', 'manager', 'secretary', 'special_officer', 'vice_president'], true)) {
            return back()->with('error', 'Only trainees and new hires can be approved here.');
        }

        $grade   = $trainee->traineeGrade;

        $effective = (float) ($grade?->final_grade ?? $grade?->total_percentage ?? 0);
        if (! $grade || $effective < TraineeGrade::PASSING_GRADE) {
            return back()->with('error', 'Trainee must have at least ' . TraineeGrade::PASSING_GRADE . '% grade to be approved.');
        }

        // Confirm & Complete: the employee becomes active and leaves the
        // active trainee list (picked up by Onboarding for Staff conversion).
        // Position is kept as-is so manager/supervisor hires stay managers.
        // This is the HR approval: employment becomes Regular with an
        // effective regularization date, and the review is recorded.
        $trainee->update(['is_active' => true, 'employment_status' => 'Regular', 'regularization_date' => now()->toDateString()]);
        $grade->update([
            'passed_to_hr' => true,
            'evaluation_status' => 'Passed',
            'hr_review_status' => 'Approved',
            'hr_reviewer_id' => $request->user()?->id,
            'hr_reviewed_at' => now(),
        ]);

        return back()->with('message', 'Employee approved! Evaluation marked as Passed.');
    }

    public function fail(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string']);

        $trainee = User::findOrFail($id);
        self::authorizeTraineeAccess($request->user(), $trainee);

        // End employment: fails the trainee and marks them inactive.
        // Training failure (not termination): the 6-month same-job
        // cooldown applies, other jobs stay open.
        ReapplyCooldown::recordTrainingFailure($trainee, $request->input('reason'));
        $trainee->traineeGrade?->update([
            'evaluation_status' => 'Failed',
            'hr_review_status' => 'Rejected',
            'hr_reviewer_id' => $request->user()?->id,
            'hr_review_notes' => $request->input('reason'),
            'hr_reviewed_at' => now(),
        ]);

        return back()->with('message', 'Employment ended. Trainee archived.');
    }
}
