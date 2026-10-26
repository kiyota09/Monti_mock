<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmPosition;
use App\Models\Hrm\TraineeGrade;
use App\Models\Hrm\TraineeDailyObservation;
use App\Models\Hrm\TraineeWeeklyEvaluation;
use App\Models\Hrm\ProbationEvaluation;
use App\Http\Controllers\Hrm\Workforce\ProbationEvaluationController;
use App\Support\ReapplyCooldown;
use App\Traits\HasPagePermissions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssignedTraineesController extends Controller
{
    use HasPagePermissions;

    private const TRAINING_WEEKS = 8;

    /**
     * My Assigned Trainees — strictly rows where users.manager_id == me.
     * Even other HR managers/supervisors cannot see rows assigned to someone else.
     */
    public function index(Request $request)
    {
        $viewer = $request->user();
        $with = [
            'traineeGrade',
            'trainingEnrollments.training',
            'traineeTransfers',
            'hrmOrgPosition:id,name',
            'hrmDepartment:id,name',
            'hrmEmploymentType:id,name',
            'manager:id,name',
            'weeklyEvaluations' => fn ($q) => $q->orderBy('week_number'),
        ];

        $assignedTrainees = User::where('position', 'trainee')
            ->where('manager_id', $viewer?->id)
            ->with($with)
            ->orderByDesc('created_at')
            ->take(100)
            ->get();

        $assignedNewHires = User::whereIn('position', ['staff', 'supervisor', 'manager', 'secretary', 'special_officer', 'vice_president'])
            ->where('manager_id', $viewer?->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('created_at', '>=', now()->subDays(60))
                    ->orWhereNotNull('training_duration_weeks');
            })
            ->with($with)
            ->orderByDesc('created_at')
            ->take(100)
            ->get();

        $users = $assignedTrainees->concat($assignedNewHires)
            ->sortByDesc('created_at')
            ->values();

        $appliedMap = Applicant::whereIn('email', $users->pluck('email')->filter()->unique()->all())
            ->orderBy('id')->get()->keyBy('email');

        $rows = $users->map(function ($u) use ($appliedMap) {
            $row = self::assignedRow($u, $appliedMap[$u->email]->position_applied ?? null);
            $employmentType = $u->hrmEmploymentType?->name;
            $isTraining = $u->position === 'trainee' || ! empty($u->training_duration_weeks);
            $isProbationary = ! $isTraining && empty($u->regularization_date) && (
                str_contains(strtolower((string) $employmentType), 'probation')
                || ! empty($u->probation_end_date)
                || str_contains(strtolower((string) $u->employment_status), 'probation')
            );

            return array_merge($row, [
                'assignment_type' => $isTraining ? 'training' : ($isProbationary ? 'probationary' : null),
                'employment_type' => $employmentType ?: '—',
                'probation_start' => $u->join_date ? (string) $u->join_date : null,
                'probation_end' => $u->probation_end_date ? (string) $u->probation_end_date : null,
            ]);
        })->values();
        $probationEvaluationMap = ProbationEvaluation::whereIn('user_id', $rows->where('assignment_type', 'probationary')->pluck('id'))
            ->orderByDesc('period_end')->orderByDesc('id')->get()->groupBy('user_id')->map->first();
        $rows = $rows->map(function ($row) use ($probationEvaluationMap) {
            $record = $probationEvaluationMap->get($row['id']);
            $row['probation_evaluation'] = $record ? [
                'id' => $record->id, 'period_label' => $record->period_label,
                'period_start' => $record->period_start?->toDateString(), 'period_end' => $record->period_end?->toDateString(),
                'due_date' => $record->due_date?->toDateString(), 'scores' => $record->scores,
                'overall_score' => $record->overall_score, 'comments' => $record->comments,
                'strengths' => $record->strengths, 'improvements' => $record->improvements,
                'recommendation' => $record->recommendation, 'status' => $record->status,
            ] : null;
            return $row;
        });
        $trainingRows = $rows->where('assignment_type', 'training')->values();
        $probationaryRows = $rows->where('assignment_type', 'probationary')->values();

        return Inertia::render('Dashboard/HRM_NEW/MyAssignedTrainees', [
            'trainees' => $trainingRows,
            'probationary' => $probationaryRows,
            'summary' => self::summary($trainingRows),
            'departments' => HrmDepartment::whereNull('archived_at')->orderBy('name')->select('id', 'name')->get(),
            'positions' => HrmPosition::whereNull('archived_at')->orderBy('name')->select('id', 'name', 'department_id')->get(),
            'programs' => $rows->pluck('training_program')->filter()->unique()->values()->all(),
            'weights' => TraineeGrade::EVALUATION_WEIGHTS,
            'passingGrade' => TraineeGrade::PASSING_GRADE,
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    /**
     * Strict assigned-only for this page: only the reporting manager
     * (users.manager_id == viewer) may open/evaluate. CEO + higher-ups keep
     * full view; HRM keeps the unassigned pool.
     */
    protected static function authorizeAssignedAccess($viewer, User $trainee): void
    {
        if (! $viewer) {
            abort(403, 'Unauthorized.');
        }
        if (strtoupper((string) ($viewer->role ?? '')) === 'CEO') {
            return;
        }
        if (in_array(strtolower((string) ($viewer->position ?? '')), ['secretary', 'special_officer', 'vice_president'], true)) {
            return;
        }
        if ((int) ($trainee->manager_id ?? 0) === (int) ($viewer->id ?? 0) && (int) ($viewer->id ?? 0) !== 0) {
            return;
        }
        if (empty($trainee->manager_id) && strtoupper((string) ($viewer->role ?? '')) === 'HRM') {
            return;
        }
        abort(403, 'You can only view trainees assigned to you as reporting manager.');
    }

    public function show(Request $request, $id)
    {
        $trainee = User::with([
            'traineeGrade',
            'trainingEnrollments.training',
            'traineeTransfers',
            'hrmOrgPosition:id,name',
            'hrmDepartment:id,name',
            'manager:id,name',
            'weeklyEvaluations' => fn ($q) => $q->orderBy('week_number'),
        ])->findOrFail($id);

        self::authorizeAssignedAccess($request->user(), $trainee);

        $applied = Applicant::where('email', $trainee->email)->orderByDesc('id')->value('position_applied');

        return response()->json([
            'trainee' => self::assignedRow($trainee, $applied),
            'weights' => TraineeGrade::EVALUATION_WEIGHTS,
            'passing_grade' => TraineeGrade::PASSING_GRADE,
        ]);
    }

    public function saveProbationEvaluation(Request $request, $id)
    {
        $employee = User::with('hrmEmploymentType')->findOrFail($id);
        self::authorizeAssignedAccess($request->user(), $employee);
        $hasProbationRecord = str_contains(strtolower((string) $employee->hrmEmploymentType?->name), 'probation')
            || ! empty($employee->probation_end_date)
            || str_contains(strtolower((string) $employee->employment_status), 'probation');
        abort_unless($hasProbationRecord && empty($employee->regularization_date), 422, 'This employee is not assigned as probationary.');

        $data = $request->validate([
            'evaluation_id' => 'nullable|integer|exists:hrm_probation_evaluations,id',
            'period_label' => 'required|string|max:80',
            'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start',
            'scores' => 'required|array', 'scores.*' => 'required|numeric|min:1|max:5',
            'strengths' => 'nullable|string|max:5000', 'improvements' => 'nullable|string|max:5000',
            'comments' => 'nullable|string|max:10000',
            'recommendation' => 'required|in:Continue probation,Improvement plan,Regularize,Do not regularize',
            'submit' => 'sometimes|boolean',
        ]);
        foreach (array_keys(ProbationEvaluationController::CRITERIA) as $criterion) {
            if (! array_key_exists($criterion, $data['scores'])) {
                return response()->json(['message' => 'Please score all six evaluation criteria.'], 422);
            }
        }
        if (in_array($data['recommendation'], ['Regularize', 'Do not regularize'], true)
            && ! str_contains(strtolower($data['period_label']), 'final evaluation')) {
            return response()->json(['message' => 'Regularization and non-regularization recommendations are reserved for final evaluations.'], 422);
        }

        $evaluation = ! empty($data['evaluation_id'])
            ? ProbationEvaluation::where('user_id', $employee->id)->findOrFail($data['evaluation_id'])
            : new ProbationEvaluation(['user_id' => $employee->id, 'evaluator_id' => $request->user()->id]);
        abort_unless($evaluation->status === null || $evaluation->status === 'Draft', 422, 'This evaluation has already been submitted.');
        $criteriaScores = collect($data['scores'])->only(array_keys(ProbationEvaluationController::CRITERIA));
        $evaluation->fill([
            'period_label' => $data['period_label'], 'period_start' => $data['period_start'],
            'period_end' => $data['period_end'], 'due_date' => $employee->probation_end_date,
            'scores' => $criteriaScores->all(), 'overall_score' => round($criteriaScores->avg() * 20, 2),
            'strengths' => $data['strengths'] ?? null, 'improvements' => $data['improvements'] ?? null,
            'comments' => $data['comments'] ?? null, 'recommendation' => $data['recommendation'],
            'status' => ($data['submit'] ?? false) ? 'Submitted' : 'Draft',
        ])->save();

        return response()->json(['evaluation' => $evaluation->fresh()]);
    }

    /**
     * Weekly evaluation (1–5 scale). Overall = average → x/5.
     */
    public function weekly(Request $request, $id)
    {
        $validated = $request->validate([
            'week_number' => 'required|integer|min:1|max:12',
            'attendance' => 'required|numeric|min:1|max:5',
            'knowledge' => 'required|numeric|min:1|max:5',
            'practical_skills' => 'required|numeric|min:1|max:5',
            'work_behavior' => 'required|numeric|min:1|max:5',
            'safety_compliance' => 'required|numeric|min:1|max:5',
            'notes' => 'nullable|string|max:2000|required_if:recommendation,early_termination',
            'recommendation' => 'required|in:continue,ready_final,early_termination',
        ]);

        $trainee = User::findOrFail($id);
        self::authorizeAssignedAccess($request->user(), $trainee);

        // Training already ended (e.g. early termination) — remaining weeks
        // are cancelled, nothing more can be evaluated.
        if (! $trainee->is_active) {
            $msg = 'Training has ended for ' . $trainee->name . ' — remaining weeks are cancelled.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $msg], 422);
            }

            return back()->withErrors(['weekly' => $msg]);
        }

        $overall = TraineeWeeklyEvaluation::overallFor($validated);

        $weekly = TraineeWeeklyEvaluation::updateOrCreate(
            ['user_id' => $trainee->id, 'week_number' => $validated['week_number']],
            [
                'attendance' => $validated['attendance'],
                'knowledge' => $validated['knowledge'],
                'practical_skills' => $validated['practical_skills'],
                'work_behavior' => $validated['work_behavior'],
                'safety_compliance' => $validated['safety_compliance'],
                'overall' => $overall,
                'notes' => $validated['notes'] ?? null,
                'recommendation' => $validated['recommendation'],
                'evaluated_by' => $request->user()->id,
                'evaluated_at' => now(),
            ]
        );

        $grade = $trainee->traineeGrade;
        if ($grade) {
            $grade->update([
                'weekly_recommendation' => $validated['recommendation'],
                'weekly_notes' => $validated['notes'] ?? $grade->weekly_notes,
            ]);
        }

        // Early termination: no final evaluation will happen — the remaining
        // training is automatically cancelled (training_status → Cancelled).
        // Training failure (not termination): 6-month same-job cooldown,
        // applicant portal stays usable for other jobs.
        if ($validated['recommendation'] === 'early_termination') {
            ReapplyCooldown::recordTrainingFailure($trainee, $validated['notes'] ?? 'Early termination — failed training');
        }

        if ($request->wantsJson()) {
            $trainee->loadMissing(['weeklyEvaluations' => fn ($q) => $q->orderBy('week_number')]);
            $applied = Applicant::where('email', $trainee->email)->orderByDesc('id')->value('position_applied');

            return response()->json([
                'message' => "Week {$validated['week_number']} evaluation saved ({$overall}/5).",
                'overall' => $overall,
                'weekly' => $weekly,
                'trainee' => self::assignedRow($trainee->fresh(['traineeGrade', 'trainingEnrollments.training', 'traineeTransfers', 'hrmOrgPosition', 'hrmDepartment', 'manager', 'weeklyEvaluations']), $applied),
            ]);
        }

        return back()->with('message', "Week {$validated['week_number']} evaluation saved ({$overall}/5).");
    }

    /**
     * Final evaluation (0–100 weighted). Weights: 20/25/30/15/10.
     */
    public function final(Request $request, $id)
    {
        $validated = $request->validate([
            'attendance' => 'required|numeric|min:0|max:100',
            'knowledge' => 'required|numeric|min:0|max:100',
            'practical_skills' => 'required|numeric|min:0|max:100',
            'work_behavior' => 'required|numeric|min:0|max:100',
            'safety_compliance' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
            'recommendation' => 'required|in:regularization,reassigned,do_not_recommend',
        ]);

        $trainee = User::findOrFail($id);
        self::authorizeAssignedAccess($request->user(), $trainee);

        // Early-terminated training is cancelled — no final evaluation happens.
        if (! $trainee->is_active) {
            $msg = 'Training has ended for ' . $trainee->name . ' — no final evaluation will happen.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $msg], 422);
            }

            return back()->withErrors(['final' => $msg]);
        }

        // Lock: once the final evaluation is submitted it cannot be modified.
        if ($trainee->traineeGrade?->final_grade !== null) {
            $msg = 'Final evaluation is already submitted and locked.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $msg], 422);
            }

            return back()->withErrors(['final' => $msg]);
        }

        // Final unlocks when every weekly evaluation is finished — or as soon
        // as the manager marks any week "Ready for Final Evaluation", in which
        // case the remaining training days are auto-passed and the trainee no
        // longer needs to attend training.
        $requiredWeeks = max(1, min(52, (int) ($trainee->training_duration_weeks ?? self::TRAINING_WEEKS) ?: self::TRAINING_WEEKS));
        $doneWeeks = TraineeWeeklyEvaluation::where('user_id', $trainee->id)->distinct('week_number')->count('week_number');
        $readyForFinal = TraineeWeeklyEvaluation::where('user_id', $trainee->id)->where('recommendation', 'ready_final')->exists();
        if ($doneWeeks < $requiredWeeks && ! $readyForFinal) {
            $msg = "Finish all weekly evaluations first ({$doneWeeks}/{$requiredWeeks} weeks done).";
            if ($request->wantsJson()) {
                return response()->json(['message' => $msg], 422);
            }

            return back()->withErrors(['final' => $msg]);
        }

        $final = TraineeGrade::computeFinalGrade($validated);
        $passed = $final >= TraineeGrade::PASSING_GRADE;

        $payload = [
            'attendance' => $validated['attendance'],
            'knowledge' => $validated['knowledge'],
            'practical_skills' => $validated['practical_skills'],
            'work_behavior' => $validated['work_behavior'],
            'safety_compliance' => $validated['safety_compliance'],
            'final_grade' => $final,
            'total_percentage' => $final,
            'manager_recommendation' => $validated['recommendation'],
            'final_notes' => $validated['notes'] ?? null,
            'remarks' => $validated['notes'] ?? null,
            'evaluated_at' => now(),
            'evaluated_by' => $request->user()->id,
        ];

        // Needs Improvement / Reassigned (moved here from the weekly
        // recommendation) are recorded as Evaluated with no retraining flag
        // and no deactivation — HR sees them and decides (approve / extend /
        // do not regularize, or transfer for a reassignment).
        if (in_array($validated['recommendation'], ['regularization', 'needs_improvement', 'reassigned'], true) && $passed) {
            // Submitted for HR review — the manager recommends, HR decides.
            // passed_to_hr + Regular employment happen only on HR approval.
            $payload['evaluation_status'] = 'Evaluated';
            $payload['passed_to_hr'] = false;
            $trainee->update(['is_active' => true]);
        } elseif ($validated['recommendation'] === 'extend') {
            $payload['evaluation_status'] = 'Evaluated';
            $payload['passed_to_hr'] = false;
            $payload['retraining'] = true;
            $payload['retraining_count'] = ((int) ($trainee->traineeGrade?->retraining_count ?? 0)) + 1;
        } else {
            $payload['evaluation_status'] = 'Failed';
            $payload['passed_to_hr'] = false;
            if ($validated['recommendation'] === 'do_not_recommend') {
                // Training failure (not termination): 6-month same-job
                // cooldown, applicant portal stays usable for other jobs.
                ReapplyCooldown::recordTrainingFailure($trainee, $validated['notes'] ?? 'Not recommended — failed final evaluation');
            }
        }

        $grade = TraineeGrade::updateOrCreate(['user_id' => $trainee->id], $payload);

        if ($request->wantsJson()) {
            $applied = Applicant::where('email', $trainee->email)->orderByDesc('id')->value('position_applied');

            return response()->json([
                'message' => "Final evaluation submitted ({$final}/100). Awaiting HR review.",
                'final_score' => $final,
                'grade' => $grade,
                'trainee' => self::assignedRow($trainee->fresh(['traineeGrade', 'trainingEnrollments.training', 'traineeTransfers', 'hrmOrgPosition', 'hrmDepartment', 'manager', 'weeklyEvaluations']), $applied),
            ]);
        }

        return back()->with('message', "Final evaluation submitted ({$final}/100). Awaiting HR review.");
    }

    /**
     * Daily observation for a single date (Knowledge & Learning,
     * Practical Skills, Work Behavior, Safety Compliance + notes each).
     */
    public function observation(Request $request, $id)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'knowledge' => 'nullable|numeric|min:1|max:5',
            'practical_skills' => 'nullable|numeric|min:1|max:5',
            'work_behavior' => 'nullable|numeric|min:1|max:5',
            'safety_compliance' => 'nullable|numeric|min:1|max:5',
            'knowledge_notes' => 'nullable|string|max:2000',
            'practical_skills_notes' => 'nullable|string|max:2000',
            'work_behavior_notes' => 'nullable|string|max:2000',
            'safety_compliance_notes' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $trainee = User::findOrFail($id);
        self::authorizeAssignedAccess($request->user(), $trainee);

        $date = Carbon::parse($validated['date'])->toDateString();

        $observation = TraineeDailyObservation::updateOrCreate(
            ['user_id' => $trainee->id, 'date' => $date],
            [
                'knowledge' => $validated['knowledge'] ?? null,
                'practical_skills' => $validated['practical_skills'] ?? null,
                'work_behavior' => $validated['work_behavior'] ?? null,
                'safety_compliance' => $validated['safety_compliance'] ?? null,
                'knowledge_notes' => $validated['knowledge_notes'] ?? null,
                'practical_skills_notes' => $validated['practical_skills_notes'] ?? null,
                'work_behavior_notes' => $validated['work_behavior_notes'] ?? null,
                'safety_compliance_notes' => $validated['safety_compliance_notes'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'evaluated_by' => $request->user()->id,
                'evaluated_at' => now(),
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Observation saved for {$date}.",
                'observation' => $observation,
            ]);
        }

        return back()->with('message', "Observation saved for {$date}.");
    }

    /**
     * Record Time In / Time Out for the assigned trainee.
     * Clicking Time In creates today's attendance log; the attendance
     * section then shows that employee's records.
     */
    public function clock(Request $request, $id)
    {
        $validated = $request->validate(['action' => 'required|in:in,out']);

        $trainee = User::findOrFail($id);
        self::authorizeAssignedAccess($request->user(), $trainee);

        // Same canonical table + Manila date the Attendance page reads
        // (Workforce\AttendanceController filters attendance_logs by
        // Carbon::today('Asia/Manila')), so Time In here shows up there.
        $today = now('Asia/Manila')->startOfDay();
        $attendance = AttendanceLog::where('user_id', $trainee->id)
            ->where('date', $today)
            ->first();

        $now = now('Asia/Manila')->format('H:i:s');

        if ($validated['action'] === 'in') {
            if ($attendance && $attendance->clock_in && ! $attendance->clock_out) {
                return back()->with('error', $trainee->name . ' is already timed in. Please time out first.');
            }
            if (! $attendance) {
                AttendanceLog::create([
                    'user_id' => $trainee->id,
                    'date' => $today,
                    'clock_in' => $now,
                    'status' => 'Present',
                ]);
            } else {
                $attendance->update(['clock_in' => $now, 'status' => 'Present']);
            }

            return back()->with('message', 'Time in recorded for ' . $trainee->name . ' at ' . $now);
        }

        if (! $attendance || ! $attendance->clock_in) {
            return back()->with('error', 'Time in first before timing out.');
        }
        if ($attendance->clock_out) {
            return back()->with('error', $trainee->name . ' has already timed out today.');
        }
        $attendance->update(['clock_out' => $now]);

        return back()->with('message', 'Time out recorded for ' . $trainee->name . ' at ' . $now);
    }

    public static function assignedRow(User $t, ?string $appliedPosition = null): array
    {
        $base = TraineeController::traineeRow($t, $appliedPosition);

        // Training schedule comes from Create Employee (deployment start +
        // duration/end calendar); fall back to join_date + default weeks.
        // When the trainee has been reassigned, the week calendar restarts
        // from the latest transfer's effective date (duration stays the same).
        $weeks = (int) ($t->training_duration_weeks ?? self::TRAINING_WEEKS);
        $weeks = max(1, min(52, $weeks ?: self::TRAINING_WEEKS));
        try {
            $latestTransfer = \App\Models\Hrm\TraineeTransfer::where('user_id', $t->id)
                ->whereNotNull('effective_date')
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->first();
            $start = null;
            if ($latestTransfer) {
                $start = Carbon::parse($latestTransfer->effective_date);
            } elseif ($t->training_start_date) {
                $start = Carbon::parse($t->training_start_date);
            } elseif ($t->join_date) {
                $start = Carbon::parse($t->join_date);
            } elseif ($t->created_at) {
                $start = Carbon::parse($t->created_at);
            } else {
                $start = now();
            }
        } catch (\Throwable $e) {
            $start = now();
        }
        try {
            $expectedEnd = (clone $start)->addWeeks($weeks);
        } catch (\Throwable $e) {
            $expectedEnd = (clone $start)->addWeeks($weeks);
        }
        $elapsed = max(0, (int) $start->diffInDays(now(), false));
        $currentWeek = (int) min($weeks, max(1, (int) ceil(($elapsed + 1) / 7)));

        $enrollments = $t->trainingEnrollments ?? collect();
        $program = $enrollments->first()?->training?->name
            ?? (($t->hrmDepartment?->name ?? $t->department ?? $t->role) . ' Training');

        $grade = $t->traineeGrade;
        $weeklies = ($t->weeklyEvaluations ?? collect())->map(fn ($w) => [
            'id' => $w->id,
            'week_number' => (int) $w->week_number,
            'attendance' => $w->attendance !== null ? (float) $w->attendance : null,
            'knowledge' => $w->knowledge !== null ? (float) $w->knowledge : null,
            'practical_skills' => $w->practical_skills !== null ? (float) $w->practical_skills : null,
            'work_behavior' => $w->work_behavior !== null ? (float) $w->work_behavior : null,
            'safety_compliance' => $w->safety_compliance !== null ? (float) $w->safety_compliance : null,
            'overall' => $w->overall !== null ? (float) $w->overall : null,
            'notes' => $w->notes,
            'recommendation' => $w->recommendation,
            'evaluated_at' => $w->evaluated_at,
        ])->values()->all();
        $latestWeekly = collect($weeklies)->sortByDesc('week_number')->first();

        $status = self::displayStatus($t, $start, $expectedEnd, $currentWeek, $latestWeekly);
        $latestEval = $latestWeekly['overall'] ?? ($grade?->final_grade !== null ? round(((float) $grade->final_grade) / 20, 2) : null);

        // Attendance for this employee (recorded via Time In / Time Out here).
        // Only show logs from the (re)assignment start date forward — older
        // records belong to the previous assignment's calendar.
        $logs = AttendanceLog::where('user_id', $t->id)
            ->where('date', '>=', $start->toDateString())
            ->orderByDesc('date')->take(14)->get();
        $fmtDay = fn ($r) => $r->date instanceof \Carbon\Carbon ? $r->date->toDateString() : (string) $r->date;
        $attendanceToday = AttendanceLog::where('user_id', $t->id)->where('date', now('Asia/Manila')->startOfDay())->first();
        $attMonth = AttendanceLog::where('user_id', $t->id)->where('date', '>=', now('Asia/Manila')->startOfMonth()->toDateString())->get();
        $attendance = [
            'today' => $attendanceToday ? [
                'date' => $fmtDay($attendanceToday),
                'clock_in' => $attendanceToday->clock_in,
                'clock_out' => $attendanceToday->clock_out,
                'status' => $attendanceToday->status,
                'clocked_in' => (bool) ($attendanceToday->clock_in && ! $attendanceToday->clock_out),
                'clocked_out' => (bool) ($attendanceToday->clock_in && $attendanceToday->clock_out),
            ] : null,
            'recent' => $logs->map(fn ($r) => [
                'date' => $fmtDay($r),
                'clock_in' => $r->clock_in,
                'clock_out' => $r->clock_out,
                'status' => $r->status,
            ])->values()->all(),
            'month_present' => $attMonth->where('status', 'present')->count(),
            'month_total' => $attMonth->count(),
        ];

        return array_merge($base, [
            'training_program' => $program,
            'training_start' => $start->toDateString(),
            'expected_end' => $expectedEnd->toDateString(),
            'duration_weeks' => $weeks,
            'duration_label' => $weeks . ' weeks',
            'current_week' => $currentWeek,
            'current_week_label' => 'Week ' . $currentWeek,
            'display_status' => $status,
            'latest_evaluation' => $latestEval,
            'latest_evaluation_label' => $latestEval !== null ? number_format((float) $latestEval, 1) . '/5' : '—',
            'weekly_evaluations' => $weeklies,
            'attendance' => $attendance,
            'observations' => TraineeDailyObservation::where('user_id', $t->id)
                ->where('date', '>=', $start->toDateString())
                ->orderByDesc('date')->take(60)->get()->map(fn ($o) => [
                'date' => $o->date instanceof \Carbon\Carbon ? $o->date->toDateString() : (string) $o->date,
                'knowledge' => $o->knowledge !== null ? (float) $o->knowledge : null,
                'practical_skills' => $o->practical_skills !== null ? (float) $o->practical_skills : null,
                'work_behavior' => $o->work_behavior !== null ? (float) $o->work_behavior : null,
                'safety_compliance' => $o->safety_compliance !== null ? (float) $o->safety_compliance : null,
                'knowledge_notes' => $o->knowledge_notes,
                'practical_skills_notes' => $o->practical_skills_notes,
                'work_behavior_notes' => $o->work_behavior_notes,
                'safety_compliance_notes' => $o->safety_compliance_notes,
                'notes' => $o->notes,
                'evaluated_at' => $o->evaluated_at,
            ])->values()->all(),
            'final_score' => $grade?->final_grade !== null ? (float) $grade->final_grade : null,
            'final_breakdown' => $grade ? [
                'attendance' => $grade->attendance !== null ? (float) $grade->attendance : null,
                'knowledge' => $grade->knowledge !== null ? (float) $grade->knowledge : null,
                'practical_skills' => $grade->practical_skills !== null ? (float) $grade->practical_skills : null,
                'work_behavior' => $grade->work_behavior !== null ? (float) $grade->work_behavior : null,
                'safety_compliance' => $grade->safety_compliance !== null ? (float) $grade->safety_compliance : null,
            ] : null,
            'manager_recommendation' => $grade?->manager_recommendation,
            'final_notes' => $grade?->final_notes,
            'final_due' => now()->gte($expectedEnd) && ! ($grade?->passed_to_hr ?? false) && ($grade?->evaluation_status !== 'Passed'),
            'employment_status_label' => $t->employment_status ?? ($t->position === 'trainee' ? 'Trainee' : ucfirst((string) $t->position)),
        ]);
    }

    protected static function displayStatus(User $t, Carbon $start, Carbon $expectedEnd, int $currentWeek, $latestWeekly): string
    {
        $grade = $t->traineeGrade;
        if (($grade?->passed_to_hr ?? false) || ($grade?->evaluation_status === 'Passed')) {
            return 'Completed';
        }
        if (! $t->is_active) {
            return 'Completed';
        }
        if ((bool) ($grade?->retraining ?? false)) {
            return 'Extended';
        }
        // Manager marked a week "Ready for Final Evaluation": remaining
        // training days are auto-passed, so the trainee jumps straight to
        // Final Evaluation and no longer needs to attend training.
        $finalDone = $grade?->final_grade !== null || in_array($grade?->evaluation_status, ['Evaluated', 'Passed', 'Failed'], true);
        $readyForFinal = collect($t->weeklyEvaluations ?? [])->contains(fn ($w) => ($w->recommendation ?? null) === 'ready_final');
        if ($readyForFinal && ! $finalDone) {
            return 'Final Evaluation';
        }
        if (now()->lt($start->copy()->startOfDay())) {
            return 'Not Started';
        }
        if (now()->gte($expectedEnd) || $currentWeek >= (int) ($t->training_duration_weeks ?? self::TRAINING_WEEKS)) {
            return ($grade?->final_grade !== null || $grade?->evaluation_status === 'Evaluated') ? 'Completed' : 'Final Evaluation';
        }
        if ($currentWeek >= 3) {
            $hasCurrent = collect([$latestWeekly])->filter(fn ($w) => $w && (int) ($w['week_number'] ?? 0) >= $currentWeek)->isNotEmpty();
            if (! $hasCurrent) {
                return 'Evaluation Due';
            }
        }

        return 'In Progress';
    }

    protected static function summary($rows): array
    {
        $by = fn ($s) => $rows->filter(fn ($r) => ($r['display_status'] ?? '') === $s)->count();
        $total = $rows->count();

        return [
            'total' => $total,
            'in_training' => $by('In Progress') + $by('Not Started'),
            'due_evaluation' => $by('Evaluation Due'),
            'final_evaluation' => $by('Final Evaluation'),
            'completed' => $by('Completed'),
            'extended' => $by('Extended'),
        ];
    }
}
