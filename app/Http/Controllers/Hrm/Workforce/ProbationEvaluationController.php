<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\ProbationEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProbationEvaluationController extends Controller
{
    public const CRITERIA = [
        'quality' => 'Quality of Work', 'productivity' => 'Productivity',
        'attendance' => 'Attendance and Punctuality', 'communication' => 'Communication',
        'teamwork' => 'Teamwork', 'job_knowledge' => 'Job Knowledge',
    ];
    public function index()
    {
        $this->ensureHrControl(request());
        $employees = User::query()->with(['hrmEmploymentType:id,name', 'hrmDepartment:id,name', 'hrmOrgPosition:id,name'])
            ->whereNull('worker_id')->where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'employee_id', 'hrm_department_id', 'hrm_position_id', 'hrm_employment_type_id', 'is_active', 'join_date', 'probation_end_date']);
        $evaluations = ProbationEvaluation::with(['employee:id,name,employee_id,hrm_department_id,hrm_position_id,hrm_employment_type_id', 'employee.hrmDepartment:id,name', 'employee.hrmOrgPosition:id,name', 'employee.hrmEmploymentType:id,name', 'evaluator:id,name', 'reviewer:id,name'])
            ->orderByDesc('created_at')->get();
        $evaluations->each(function (ProbationEvaluation $evaluation) {
            $evaluation->setAttribute('criteria_details', DB::table('hrm_evaluation_criteria')->where('evaluation_id', $evaluation->id)->orderBy('id')->get());
            $evaluation->setAttribute('objective_data', DB::table('attendance_logs')->where('user_id', $evaluation->user_id)
                ->whereBetween('date', [$evaluation->period_start, $evaluation->period_end])
                ->selectRaw('COUNT(*) as recorded_days, SUM(CASE WHEN LOWER(status) LIKE ? THEN 1 ELSE 0 END) as late_days, SUM(CASE WHEN LOWER(status) LIKE ? THEN 1 ELSE 0 END) as absent_days', ['%late%', '%absent%'])
                ->first());
        });
        $templates = DB::table('hrm_kpi_templates')->leftJoin('hrm_departments', 'hrm_departments.id', '=', 'hrm_kpi_templates.department_id')
            ->leftJoin('hrm_positions', 'hrm_positions.id', '=', 'hrm_kpi_templates.position_id')
            ->select('hrm_kpi_templates.*', 'hrm_departments.name as department_name', 'hrm_positions.name as position_name')
            ->orderBy('name')->get()->map(function ($template) {
                $template->criteria = DB::table('hrm_kpi_criteria')->where('template_id', $template->id)->orderBy('sort_order')->get();
                return $template;
            });
        $plans = DB::table('hrm_performance_improvement_plans')->join('users', 'users.id', '=', 'hrm_performance_improvement_plans.user_id')
            ->select('hrm_performance_improvement_plans.*', 'users.name as employee_name', 'users.employee_id')->latest('hrm_performance_improvement_plans.created_at')->get();
        $departments = DB::table('hrm_departments')->whereNull('archived_at')->orderBy('name')->get(['id', 'name']);
        $positions = DB::table('hrm_positions')->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'department_id']);
        $active = $evaluations->whereNotIn('status', ['Completed', 'Approved']);
        return Inertia::render('Dashboard/HRM_NEW/PerformanceKpi', [
            'employees' => $employees, 'evaluations' => $evaluations, 'templates' => $templates,
            'plans' => $plans, 'departments' => $departments, 'positions' => $positions,
            'criteria' => self::CRITERIA,
            'metrics' => [
                'employees' => $employees->count(), 'pending' => $active->whereIn('status', ['Draft', 'Assigned'])->count(),
                'inProgress' => $active->where('status', 'In Progress')->count(), 'submitted' => $active->where('status', 'Submitted')->count(),
                'forReview' => $active->whereIn('status', ['Manager Review', 'HR Review', 'Pending Approval'])->count(),
                'finalized' => $evaluations->whereIn('status', ['Approved', 'Completed'])->count(),
                'improvement' => $evaluations->whereIn('classification', ['Needs Improvement', 'Unsatisfactory'])->count(),
            ],
        ]);
    }

    public function mine(Request $request)
    {
        $evaluations = ProbationEvaluation::with(['evaluator:id,name', 'reviewer:id,name'])
            ->where('user_id', $request->user()->id)->whereIn('status', ['Approved', 'Completed'])
            ->orderByDesc('period_end')->get();
        $evaluations->each(function (ProbationEvaluation $evaluation) {
            $evaluation->setAttribute('criteria_details', DB::table('hrm_evaluation_criteria')->where('evaluation_id', $evaluation->id)->orderBy('id')->get());
        });
        return Inertia::render('Dashboard/HRM_NEW/MyPerformance', ['evaluations' => $evaluations]);
    }

    public function store(Request $request)
    {
        $this->ensureHrControl($request);
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id', 'evaluation_type' => 'required|in:probationary,regular',
            'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start',
            'due_date' => 'nullable|date', 'evaluator_id' => 'nullable|exists:users,id', 'manager_id' => 'nullable|exists:users,id',
            'template_id' => 'nullable|integer|exists:hrm_kpi_templates,id',
        ]);
        $employee = User::with('hrmEmploymentType')->findOrFail($data['user_id']);
        abort_unless($employee->is_active && !$employee->worker_id, 422, 'Select an active direct employee.');
        $type = strtolower((string) $employee->hrmEmploymentType?->name);
        if ($data['evaluation_type'] === 'probationary') {
            abort_unless($type === 'probationary', 422, 'Choose a probationary employee.');
        } else {
            abort_unless($type === 'regular', 422, 'Choose a regular employee.');
        }
        $duplicate = ProbationEvaluation::where('user_id', $employee->id)->where('evaluation_type', $data['evaluation_type'])
            ->whereDate('period_start', $data['period_start'])->whereDate('period_end', $data['period_end'])
            ->whereNotIn('status', ['Completed', 'Approved'])->exists();
        abort_if($duplicate, 422, 'An active evaluation already exists for this employee and period.');
        $template = null;
        if (!empty($data['template_id'])) {
            $template = DB::table('hrm_kpi_templates')->where('id', $data['template_id'])->where('status', 'active')->first();
            abort_unless($template && $template->evaluation_type === $data['evaluation_type'], 422, 'Choose an active matching KPI template.');
            $criteria = DB::table('hrm_kpi_criteria')->where('template_id', $template->id)->orderBy('sort_order')->get();
            abort_unless($criteria->isNotEmpty() && round((float) $criteria->sum('weight'), 2) === 100.0, 422, 'The active template criteria weights must total exactly 100%.');
        }
        $evaluation = DB::transaction(function () use ($data, $employee, $template) {
            User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $duplicate = ProbationEvaluation::where('user_id', $employee->id)->where('evaluation_type', $data['evaluation_type'])
                ->whereDate('period_start', $data['period_start'])->whereDate('period_end', $data['period_end'])
                ->whereNotIn('status', ['Completed', 'Approved'])->exists();
            abort_if($duplicate, 422, 'An active evaluation already exists for this employee and period.');
            $evaluation = ProbationEvaluation::create([
                'user_id' => $employee->id, 'evaluator_id' => $data['evaluator_id'] ?? request()->user()->id,
                'manager_id' => $data['manager_id'] ?? null, 'period_start' => $data['period_start'], 'period_end' => $data['period_end'],
                'due_date' => $data['due_date'] ?? null, 'period_label' => $data['evaluation_type'] === 'probationary' ? 'Final evaluation' : 'Regular employee evaluation',
                'evaluation_type' => $data['evaluation_type'], 'template_id' => $template->id ?? null,
                'template_snapshot' => $template ? (array) $template : null,
                'employment_status_snapshot' => $employee->is_active ? 'Active' : 'Inactive', 'employment_type_snapshot' => $employee->hrmEmploymentType?->name,
                'status' => 'Assigned',
            ]);
            if ($template) {
                foreach (DB::table('hrm_kpi_criteria')->where('template_id', $template->id)->orderBy('sort_order')->get() as $criterion) {
                    DB::table('hrm_evaluation_criteria')->insert([
                        'evaluation_id' => $evaluation->id, 'criterion_id' => $criterion->id, 'category' => $criterion->category,
                        'name' => $criterion->name, 'description' => $criterion->description, 'weight' => $criterion->weight,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            $this->audit($evaluation, request()->user()->id, 'assigned', null, 'Assigned');
            return $evaluation;
        });
        return response()->json(['evaluation' => $evaluation], 201);
    }

    public function update(Request $request, ProbationEvaluation $evaluation)
    {
        $this->ensureEvaluator($request, $evaluation);
        abort_unless(in_array($evaluation->status, ['Draft', 'Assigned', 'In Progress', 'Returned for Revision'], true), 422, 'This evaluation is no longer editable.');
        $data = $request->validate([
            'scores' => 'required|array', 'scores.*' => 'nullable|numeric|min:1|max:5',
            'criterion_comments' => 'nullable|array', 'criterion_comments.*' => 'nullable|string|max:5000',
            'comments' => 'nullable|string|max:10000', 'strengths' => 'nullable|string|max:5000', 'improvements' => 'nullable|string|max:5000',
            'recommendation' => 'required|string|max:60', 'submit' => 'sometimes|boolean',
        ]);
        $criteria = DB::table('hrm_evaluation_criteria')->where('evaluation_id', $evaluation->id)->get();
        $weights = [];
        foreach ($criteria as $criterion) $weights[(string) $criterion->criterion_id] = (float) $criterion->weight;
        if (!$weights) $weights = array_fill_keys(array_keys(self::CRITERIA), 100 / count(self::CRITERIA));
        $scoreMap = $data['scores'];
        foreach (array_keys($weights) as $key) if (!isset($scoreMap[$key]) || $scoreMap[$key] === '') return response()->json(['message' => 'Rate every required KPI criterion before saving.'], 422);
        $overall = self::calculateWeightedScore($scoreMap, $weights);
        $classification = self::classifyScore($overall);
        $submit = (bool) ($data['submit'] ?? false);
        if ($submit && empty($data['comments'])) return response()->json(['message' => 'Evaluator comments are required before submission.'], 422);
        DB::transaction(function () use ($evaluation, $data, $criteria, $scoreMap, $weights, $overall, $classification, $submit) {
            foreach ($criteria as $criterion) {
                $key = (string) $criterion->criterion_id;
                DB::table('hrm_evaluation_criteria')->where('id', $criterion->id)->update([
                    'rating' => (int) $scoreMap[$key], 'weighted_score' => round(((float) $scoreMap[$key] / 5) * (float) $criterion->weight, 2),
                    'comments' => $data['criterion_comments'][$key] ?? null, 'updated_at' => now(),
                ]);
            }
            $from = $evaluation->status;
            $evaluation->update([
                'scores' => $scoreMap, 'overall_score' => $overall, 'classification' => $classification,
                'comments' => $data['comments'] ?? null, 'strengths' => $data['strengths'] ?? null,
                'improvements' => $data['improvements'] ?? null, 'recommendation' => $data['recommendation'],
                'status' => $submit ? 'Submitted' : 'In Progress',
            ]);
            $this->audit($evaluation, request()->user()->id, $submit ? 'submitted' : 'saved', $from, $evaluation->status);
        });
        return response()->json(['evaluation' => $evaluation->fresh(['employee', 'evaluator', 'reviewer'])]);
    }

    public function review(Request $request, ProbationEvaluation $evaluation)
    {
        $this->ensureHrControl($request);
        abort_unless($evaluation->status === 'Submitted' || $evaluation->status === 'Manager Review' || $evaluation->status === 'HR Review', 422, 'Only submitted evaluations can be reviewed.');
        $data = $request->validate(['decision' => 'required|in:Approve,Return,Regularize,Further review,Do not regularize,Recommend Regularization,Recommend Separation Review,Recommend Improvement,Recommend Training,Promotion Consideration,Excellent Performance,Meets Expectations', 'review_notes' => 'nullable|string|max:10000']);
        $from = $evaluation->status;
        $evaluation->update([
            'reviewer_id' => $request->user()->id, 'review_notes' => $data['review_notes'] ?? null,
            'status' => $data['decision'] === 'Return' ? 'Returned for Revision' : 'Approved',
            'hr_recommendation' => str_starts_with($data['decision'], 'Recommend ') || in_array($data['decision'], ['Regularize', 'Further review', 'Do not regularize'], true) ? $data['decision'] : $evaluation->hr_recommendation,
            'management_decision' => $data['decision'] === 'Approve' ? 'Approved by HR' : $evaluation->management_decision,
        ]);
        $this->audit($evaluation, $request->user()->id, 'reviewed', $from, $evaluation->status, ['decision' => $data['decision']]);
        return response()->json(['evaluation' => $evaluation->fresh(['employee', 'evaluator', 'reviewer'])]);
    }

    public function managerReview(Request $request, ProbationEvaluation $evaluation)
    {
        abort_unless((int) $evaluation->manager_id === (int) $request->user()->id, 403, 'Only the assigned reviewing manager may review this evaluation.');
        abort_unless($evaluation->status === 'Submitted', 422, 'Only submitted evaluations can enter manager review.');
        $data = $request->validate(['decision' => 'required|in:Approve,Return', 'review_notes' => 'nullable|string|max:10000']);
        $from = $evaluation->status;
        $evaluation->update([
            'reviewer_id' => $request->user()->id, 'review_notes' => $data['review_notes'] ?? null,
            'status' => $data['decision'] === 'Approve' ? 'HR Review' : 'Returned for Revision',
        ]);
        $this->audit($evaluation, $request->user()->id, 'manager_reviewed', $from, $evaluation->status, ['decision' => $data['decision']]);
        return response()->json(['evaluation' => $evaluation->fresh(['employee', 'evaluator', 'reviewer'])]);
    }

    public function saveTemplate(Request $request)
    {
        $this->ensureHrControl($request);
        $data = $request->validate([
            'id' => 'nullable|integer|exists:hrm_kpi_templates,id', 'name' => 'required|string|max:160',
            'department_id' => 'nullable|exists:hrm_departments,id', 'position_id' => 'nullable|exists:hrm_positions,id',
            'evaluation_type' => 'required|in:probationary,regular', 'description' => 'nullable|string|max:5000',
            'frequency' => 'required|in:monthly,quarterly,semiannual,annual,probation_milestone', 'effective_date' => 'nullable|date',
            'status' => 'required|in:draft,active,archived', 'criteria' => 'required|array|min:1',
            'criteria.*.category' => 'required|string|max:120', 'criteria.*.name' => 'required|string|max:160',
            'criteria.*.weight' => 'required|numeric|min:0.01|max:100', 'criteria.*.description' => 'nullable|string|max:5000',
            'criteria.*.measurement_method' => 'nullable|string|max:255', 'criteria.*.performance_standards' => 'nullable|string|max:5000',
            'criteria.*.required_evidence' => 'nullable|string|max:2000', 'criteria.*.rating_guidelines' => 'nullable|array',
        ]);
        abort_if($data['status'] === 'active' && round(collect($data['criteria'])->sum(fn ($c) => (float) $c['weight']), 2) !== 100.0, 422, 'Active template weights must total exactly 100%.');
        DB::transaction(function () use ($data, $request) {
            $id = $data['id'] ?? null;
            $template = $id ? DB::table('hrm_kpi_templates')->where('id', $id)->first() : null;
            $version = $template ? $template->version + 1 : 1;
            if ($template && $template->status === 'active') {
                DB::table('hrm_kpi_templates')->where('id', $id)->update(['status' => 'archived', 'updated_at' => now()]);
                $id = null;
            }
            $fields = ['name' => $data['name'], 'department_id' => $data['department_id'] ?? null, 'position_id' => $data['position_id'] ?? null,
                'evaluation_type' => $data['evaluation_type'], 'description' => $data['description'] ?? null, 'frequency' => $data['frequency'],
                'effective_date' => $data['effective_date'] ?? null, 'version' => $version, 'status' => $data['status'], 'updated_at' => now()];
            if ($id) DB::table('hrm_kpi_templates')->where('id', $id)->update($fields);
            else $id = DB::table('hrm_kpi_templates')->insertGetId($fields + ['created_by' => $request->user()->id, 'created_at' => now()]);
            DB::table('hrm_kpi_criteria')->where('template_id', $id)->delete();
            foreach ($data['criteria'] as $order => $criterion) DB::table('hrm_kpi_criteria')->insert([
                'template_id' => $id, 'category' => $criterion['category'], 'name' => $criterion['name'],
                'description' => $criterion['description'] ?? null, 'measurement_method' => $criterion['measurement_method'] ?? null,
                'weight' => $criterion['weight'], 'performance_standards' => $criterion['performance_standards'] ?? null,
                'required_evidence' => $criterion['required_evidence'] ?? null, 'rating_guidelines' => isset($criterion['rating_guidelines']) ? json_encode($criterion['rating_guidelines']) : null,
                'sort_order' => $order, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return response()->json(['message' => 'KPI template saved.']);
    }

    public function storePlan(Request $request)
    {
        $this->ensureHrControl($request);
        $data = $request->validate(['user_id' => 'required|exists:users,id', 'evaluation_id' => 'nullable|exists:hrm_probation_evaluations,id', 'supervisor_id' => 'nullable|exists:users,id', 'identified_issues' => 'required|string|max:10000', 'objectives' => 'required|string|max:10000', 'standards' => 'nullable|string|max:10000', 'training_or_coaching' => 'nullable|string|max:5000', 'start_date' => 'required|date', 'target_date' => 'required|date|after_or_equal:start_date']);
        DB::table('hrm_performance_improvement_plans')->insert($data + ['status' => 'active', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Improvement plan created.'], 201);
    }

    public function export(Request $request)
    {
        $this->ensureHrControl($request);
        $rows = ProbationEvaluation::with(['employee', 'evaluator', 'reviewer'])->orderByDesc('period_end')->get();
        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee ID', 'Employee', 'Evaluation Type', 'Period Start', 'Period End', 'Overall Score', 'Classification', 'Criterion Ratings', 'Evaluator Comments', 'Status', 'Evaluator', 'HR Recommendation', 'HR Review Notes']);
            foreach ($rows as $row) fputcsv($out, [$row->employee?->employee_id, $row->employee?->name, $row->evaluation_type, $row->period_start, $row->period_end, $row->overall_score, $row->classification, json_encode($row->scores), $row->comments, $row->status, $row->evaluator?->name, $row->hr_recommendation, $row->review_notes]);
            fclose($out);
        };
        return response()->streamDownload($callback, 'employee-performance-evaluations.csv', ['Content-Type' => 'text/csv']);
    }

    public function acknowledge(Request $request, ProbationEvaluation $evaluation)
    {
        abort_unless((int) $evaluation->user_id === (int) $request->user()->id, 403, 'Employees can only acknowledge their own evaluation.');
        abort_unless(in_array($evaluation->status, ['Approved', 'Completed'], true), 422, 'Only an approved evaluation can be acknowledged.');
        $data = $request->validate(['feedback' => 'nullable|string|max:10000']);
        $from = $evaluation->status;
        $evaluation->update(['acknowledged_at' => now(), 'employee_feedback' => $data['feedback'] ?? null, 'status' => 'Completed']);
        $this->audit($evaluation, $request->user()->id, 'acknowledged', $from, 'Completed');
        return response()->json(['message' => 'Evaluation receipt acknowledged.']);
    }

    private function ensureEvaluator(Request $request, ProbationEvaluation $evaluation): void
    {
        $userId = (int) $request->user()->id;
        abort_unless(strtoupper((string) $request->user()->role) === 'HRM' || in_array($userId, [(int) $evaluation->evaluator_id, (int) $evaluation->manager_id], true), 403, 'Only the assigned evaluator, reviewing manager, or HR may update this evaluation.');
    }

    public static function calculateWeightedScore(array $ratings, array $weights): float
    {
        if (!$weights || abs(array_sum(array_map('floatval', $weights)) - 100.0) > 0.001) {
            throw new \InvalidArgumentException('KPI weights must total exactly 100%.');
        }
        $score = 0.0;
        foreach ($weights as $key => $weight) {
            if (!isset($ratings[$key]) || !is_numeric($ratings[$key]) || (float) $ratings[$key] < 1 || (float) $ratings[$key] > 5) {
                throw new \InvalidArgumentException('Every KPI rating must be between 1 and 5.');
            }
            $score += ((float) $ratings[$key] / 5) * (float) $weight;
        }
        return round($score, 2);
    }

    public static function classifyScore(float $score): string
    {
        return $score >= 90 ? 'Outstanding' : ($score >= 80 ? 'Exceeds Expectations' : ($score >= 70 ? 'Meets Expectations' : ($score >= 60 ? 'Needs Improvement' : 'Unsatisfactory')));
    }

    private function audit(ProbationEvaluation $evaluation, ?int $actor, string $action, ?string $from, ?string $to, array $details = []): void
    {
        DB::table('hrm_evaluation_audit_logs')->insert(['evaluation_id' => $evaluation->id, 'actor_id' => $actor, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'details' => json_encode($details), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function ensureHrControl(Request $request): void
    {
        abort_unless(strtoupper((string) $request->user()?->role) === 'HRM', 403, 'Performance management actions are controlled by HR.');
    }
}
