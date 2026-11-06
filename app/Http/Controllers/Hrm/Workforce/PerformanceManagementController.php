<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Core\Notification;
use App\Models\Core\User;
use App\Services\Hrm\KpiScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use InvalidArgumentException;

class PerformanceManagementController extends Controller
{
    public function __construct(private readonly KpiScoringService $scoring) {}

    public function index(Request $request, bool $templatesOnly = false, bool $kpiEvaluationsOnly = false)
    {
        $this->assertViewAccess($request);
        $isHr = $this->isHr($request->user());
        $canManagePlans = $isHr || ($request->user()->position ?? null) === 'manager';
        $employees = $canManagePlans ? $this->eligibleEmployees()->when(! $isHr, fn ($query) => $query->where('hrm_department_id', $request->user()->hrm_department_id))->get([
            'id', 'name', 'employee_id', 'profile_photo_path', 'manager_id', 'hrm_department_id', 'hrm_position_id', 'hrm_employment_type_id', 'role',
        ]) : collect();
        $employees->each(function ($employee) {
            $employee->profile_photo_url = $employee->profile_photo_path ? Storage::url($employee->profile_photo_path) : null;
        });
        $evaluations = $this->evaluationQuery($request->user(), $isHr)
            ->when($kpiEvaluationsOnly, fn ($query) => $query->where('kpi.evaluator_id', $request->user()->id))
            ->when($request->filled('department'), fn ($query) => $query->where('employee.hrm_department_id', $request->integer('department')))
            ->when($request->filled('position'), fn ($query) => $query->where('employee.hrm_position_id', $request->integer('position')))
            ->when($request->filled('employee'), fn ($query) => $query->where(fn ($employeeQuery) => $employeeQuery->where('employee.name', 'like', '%' . (string) $request->string('employee') . '%')->orWhere('employee.employee_id', 'like', '%' . (string) $request->string('employee') . '%')))
            ->when($request->filled('status'), fn ($query) => $query->where('kpi.status', (string) $request->string('status')))
            ->when($request->filled('period'), function ($query) use ($request) {
                $month = substr((string) $request->string('period'), 0, 7);
                $query->where('kpi.period_start', '>=', $month . '-01')->where('kpi.period_start', '<=', date('Y-m-t', strtotime($month . '-01')));
            })
            ->orderByDesc('kpi.created_at')->get();

        $evaluations->each(function ($evaluation) use ($request) {
            $evaluation->template_snapshot = json_decode($evaluation->template_snapshot, true) ?: [];
            $evaluation->classification_snapshot = json_decode($evaluation->classification_snapshot, true) ?: [];
            $evaluation->ratings = DB::table('hrm_kpi_evaluation_ratings')->where('evaluation_id', $evaluation->id)->orderBy('sort_order')->get();
            $evaluation->ratings->each(function ($rating) {
                $rating->evidence = DB::table('hrm_kpi_evaluation_evidence')->where('evaluation_rating_id', $rating->id)->get();
            });
            $evaluation->reviews = DB::table('hrm_kpi_evaluation_reviews')->where('evaluation_id', $evaluation->id)->orderBy('created_at')->get();
            if (DB::getSchemaBuilder()->hasTable('attendance_logs')) {
                $evaluation->objective_data = DB::table('attendance_logs')->where('user_id', $evaluation->user_id)
                    ->whereBetween('date', [$evaluation->period_start, $evaluation->period_end])
                    ->selectRaw("COUNT(*) as recorded_days, SUM(CASE WHEN LOWER(status) LIKE '%late%' THEN 1 ELSE 0 END) as late_days, SUM(CASE WHEN LOWER(status) LIKE '%absent%' THEN 1 ELSE 0 END) as absent_days")
                    ->first();
            }
        });

        $templates = DB::table('hrm_kpi_templates as t')
            ->leftJoin('hrm_departments as d', 'd.id', '=', 't.department_id')
            ->leftJoin('hrm_positions as p', 'p.id', '=', 't.position_id')
            ->where('t.evaluation_type', 'regular')
            ->select('t.*', 'd.name as department_name', 'p.name as position_name')
            ->orderBy('t.name')->get();
        $templates->each(function ($template) {
            $template->criteria = DB::table('hrm_kpi_criteria')->where('template_id', $template->id)->orderBy('sort_order')->get();
        });
        $evaluators = $isHr ? User::query()->whereNull('worker_id')->where('is_active', true)
            ->whereRaw('LOWER(position) = ?', ['manager'])
            ->orderBy('name')->get(['id', 'name', 'employee_id', 'role', 'position', 'hrm_department_id']) : collect();
        $reviewManagers = $isHr ? User::query()->whereNull('worker_id')->where('is_active', true)
            ->whereHas('hrmDepartment', fn ($query) => $query->whereRaw('LOWER(name) = ?', ['human resources']))
            ->whereHas('hrmOrgPosition', fn ($position) => $position->whereRaw('LOWER(name) = ?', ['hr manager'])->where('management_level', 'Managerial'))
            ->orderBy('name')->get(['id', 'name', 'employee_id', 'role', 'position']) : collect();
        $plans = DB::table('hrm_kpi_pips as p')->join('users as u', 'u.id', '=', 'p.user_id')
            ->leftJoin('users as s', 's.id', '=', 'p.supervisor_id')
            ->select('p.*', 'u.name as employee_name', 'u.employee_id', 's.name as supervisor_name')
            ->when(! $isHr, fn ($query) => $query->where(fn ($scope) => $scope->where('p.supervisor_id', $request->user()->id)->orWhere('p.user_id', $request->user()->id)))
            ->orderByDesc('p.created_at')->get();
        $plans->each(function ($plan) {
            $plan->progress = DB::table('hrm_kpi_pip_progress')->where('pip_id', $plan->id)->orderByDesc('recorded_on')->get();
        });
        $rules = DB::table('hrm_kpi_classification_rules')->where('active', true)->orderBy('minimum_score')->get();
        $departments = DB::table('hrm_departments')->whereNull('archived_at')->orderBy('name')->get(['id', 'name']);
        $positions = DB::table('hrm_positions')->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'department_id']);
        $allRows = $evaluations;
        $completed = $allRows->where('status', 'Completed');

        return Inertia::render($templatesOnly ? 'Dashboard/HRM_NEW/KpiTemplates' : 'Dashboard/HRM_NEW/PerformanceKpi', [
            'employees' => $employees,
            'evaluators' => $evaluators,
            'reviewManagers' => $reviewManagers,
            'evaluations' => $evaluations,
            'templates' => $templates,
            'plans' => $plans,
            'rules' => $rules,
            'departments' => $departments,
            'positions' => $positions,
            'filters' => $request->only(['period', 'department', 'position', 'employee', 'status']),
            'isHr' => $isHr,
            'kpiEvaluationsOnly' => $kpiEvaluationsOnly,
            'metrics' => [
                'employees' => $isHr ? $this->eligibleEmployees()->count() : null,
                'assigned' => $allRows->count(),
                'pending' => $allRows->whereIn('status', ['Draft', 'Assigned'])->count(),
                'inProgress' => $allRows->where('status', 'In Progress')->count(),
                'submitted' => $allRows->where('status', 'Submitted')->count(),
                'managerReview' => $allRows->where('status', 'Submitted')->count(),
                'hrReview' => $allRows->where('status', 'HR Review')->count(),
                'completed' => $completed->count(),
                'averageScore' => $completed->whereNotNull('overall_score')->avg('overall_score'),
                'improvement' => $completed->whereIn('classification', ['Needs Improvement', 'Unsatisfactory'])->pluck('user_id')->unique()->count(),
            ],
        ]);
    }

    public function templates(Request $request)
    {
        return $this->index($request, true);
    }

    public function kpiEvaluations(Request $request)
    {
        return $this->index($request, false, true);
    }

    public function store(Request $request)
    {
        $this->assertHr($request);
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id', 'template_id' => 'required|integer|exists:hrm_kpi_templates,id',
            'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start',
            'due_date' => 'required|date|after_or_equal:period_start', 'evaluator_id' => 'required|integer|exists:users,id',
            'manager_id' => 'required|integer|exists:users,id', 'assignment_notes' => 'nullable|string|max:5000',
        ]);
        $employee = $this->eligibleEmployees()->with(['hrmDepartment:id,name', 'hrmOrgPosition:id,name', 'hrmEmploymentType:id,name'])->findOrFail($data['user_id']);
        abort_if((int) $employee->id === (int) $data['evaluator_id'] || (int) $employee->id === (int) $data['manager_id'], 422, 'An employee cannot evaluate or review their own KPI record.');
        $evaluatorQuery = User::whereKey($data['evaluator_id'])->where('is_active', true)->whereNull('worker_id')
            ->whereRaw('LOWER(position) = ?', ['manager']);
        $evaluatorQuery->where('hrm_department_id', $employee->hrm_department_id);
        $evaluator = $evaluatorQuery->firstOrFail();
        $manager = User::whereKey($data['manager_id'])->where('is_active', true)->whereNull('worker_id')
            ->whereHas('hrmDepartment', fn ($query) => $query->whereRaw('LOWER(name) = ?', ['human resources']))
            ->whereHas('hrmOrgPosition', fn ($position) => $position->whereRaw('LOWER(name) = ?', ['hr manager'])->where('management_level', 'Managerial'))
            ->firstOrFail();
        $template = DB::table('hrm_kpi_templates')->where('id', $data['template_id'])->where('status', 'active')->where('evaluation_type', 'regular')->first();
        abort_unless($template && (int) $template->position_id === (int) $employee->hrm_position_id, 422, 'Select an active KPI template for the employee\'s position.');
        abort_unless($template->department_id === null || (int) $template->department_id === (int) $employee->hrm_department_id, 422, 'The KPI template is not configured for the employee\'s department.');
        abort_unless(empty($template->effective_date) || $template->effective_date <= $data['period_start'], 422, 'The KPI template is not yet effective for this evaluation period.');
        $criteria = DB::table('hrm_kpi_criteria')->where('template_id', $template->id)->orderBy('sort_order')->get();
        $this->scoring->validateWeights($criteria->map(fn ($criterion) => (array) $criterion)->all());
        $classificationRules = DB::table('hrm_kpi_classification_rules')->where('active', true)->orderBy('minimum_score')->get();
        abort_if($classificationRules->isEmpty(), 422, 'Configure performance classifications before assigning evaluations.');

        $evaluation = DB::transaction(function () use ($data, $employee, $template, $criteria, $classificationRules, $request) {
            User::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $duplicate = DB::table('hrm_kpi_evaluations')->where('user_id', $employee->id)
                ->where('template_id', $template->id)->whereDate('period_start', $data['period_start'])->whereDate('period_end', $data['period_end'])
                ->where('status', '!=', 'Cancelled')->exists();
            abort_if($duplicate, 422, 'An evaluation already exists for this employee, template, and period.');
            $templateData = (array) $template;
            $templateData['criteria'] = $criteria->map(fn ($criterion) => (array) $criterion)->all();
            $ratingScale = json_decode($template->rating_scale ?? '[]', true) ?: ['1' => 'Unsatisfactory', '2' => 'Needs Improvement', '3' => 'Meets Expectations', '4' => 'Exceeds Expectations', '5' => 'Outstanding'];
            $snapshot = ['template' => $templateData, 'rating_scale' => $ratingScale];
            $evaluationId = DB::table('hrm_kpi_evaluations')->insertGetId([
                'reference' => 'KPI-' . now()->format('ym') . '-' . Str::upper(Str::random(8)),
                'user_id' => $employee->id, 'template_id' => $template->id, 'template_version' => $template->version,
                'template_snapshot' => json_encode($snapshot), 'classification_snapshot' => json_encode($classificationRules),
                'evaluator_id' => $data['evaluator_id'], 'manager_id' => $data['manager_id'],
                'period_start' => $data['period_start'], 'period_end' => $data['period_end'], 'due_date' => $data['due_date'] ?? null,
                'assignment_notes' => $data['assignment_notes'] ?? null, 'status' => 'Assigned', 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($criteria as $criterion) {
                DB::table('hrm_kpi_evaluation_ratings')->insert([
                    'evaluation_id' => $evaluationId, 'criterion_id' => $criterion->id, 'category' => $criterion->category,
                    'criterion_name' => $criterion->name, 'description' => $criterion->description,
                    'performance_standards' => $criterion->performance_standards, 'required_evidence' => $criterion->required_evidence, 'weight' => $criterion->weight,
                    'rating_guidelines' => $criterion->rating_guidelines,
                    'sort_order' => $criterion->sort_order, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->audit($evaluationId, $request->user()->id, 'assigned', null, 'Assigned');
            return DB::table('hrm_kpi_evaluations')->where('id', $evaluationId)->first();
        });

        Notification::notify($evaluator->id, 'kpi_evaluation_assigned', 'KPI evaluation assigned', "Complete {$employee->name}'s evaluation by {$data['due_date']}.", 'hrm.workforce.performance-kpi.index', $request->user()->id);
        return response()->json(['evaluation' => $evaluation], 201);
    }

    public function update(Request $request, int $evaluation)
    {
        $record = DB::table('hrm_kpi_evaluations')->where('id', $evaluation)->firstOrFail();
        abort_unless((int) $record->evaluator_id === (int) $request->user()->id, 403, 'Only the assigned evaluator can rate this evaluation.');
        abort_unless(in_array($record->status, ['Assigned', 'In Progress', 'Returned for Revision'], true), 422, 'This evaluation is locked for evaluator edits.');
        $data = $request->validate([
            'ratings' => 'required|array', 'ratings.*.rating' => 'nullable|integer|min:1|max:5',
            'ratings.*.comments' => 'nullable|string|max:5000', 'comments' => 'nullable|string|max:10000',
            'strengths' => 'nullable|string|max:5000', 'development_recommendations' => 'nullable|string|max:5000',
            'recommendation' => 'nullable|in:Excellent Performance,Meets Expectations,Performance Improvement Required,Additional Training Recommended,Promotion Consideration',
            'submit' => 'sometimes|boolean',
        ]);
        abort_if(! empty($data['submit']) && now()->toDateString() < (string) $record->period_end, 422, 'This evaluation can be submitted starting on the period end date (' . $record->period_end . ').');
        $criteria = DB::table('hrm_kpi_evaluation_ratings')->where('evaluation_id', $record->id)->orderBy('sort_order')->get();
        abort_if($criteria->isEmpty(), 422, 'The assigned evaluation has no criteria.');
        $ratings = [];
        foreach ($criteria as $criterion) {
            $incoming = $data['ratings'][(string) $criterion->id] ?? [];
            $ratings[(string) $criterion->id] = $incoming['rating'] ?? null;
            if (! empty($data['submit']) && empty($incoming['comments'])) {
                return response()->json(['message' => "Evaluator comments are required for {$criterion->criterion_name}."], 422);
            }
            if (! empty($data['submit']) && $criterion->required_evidence && ! DB::table('hrm_kpi_evaluation_evidence')->where('evaluation_rating_id', $criterion->id)->exists()) {
                return response()->json(['message' => "Supporting evidence is required for {$criterion->criterion_name}."], 422);
            }
        }
        $submit = (bool) ($data['submit'] ?? false);
        try {
            if ($submit && empty($data['comments'])) {
                return response()->json(['message' => 'Evaluator comments are required before submission.'], 422);
            }
            $criterionWeights = $criteria->map(fn ($criterion) => ['criterion_id' => (string) $criterion->id, 'weight' => $criterion->weight])->all();
            $allRated = collect($ratings)->every(fn ($rating) => is_numeric($rating) && (int) $rating >= 1 && (int) $rating <= 5);
            if ($submit || $allRated) {
                $calculation = $this->scoring->calculate($ratings, $criterionWeights);
                $rules = collect(json_decode($record->classification_snapshot, true) ?: []);
                $classification = $this->scoring->classification($calculation['overall_score'], $rules);
            } else {
                $weightedScores = [];
                foreach ($ratings as $criterionId => $rating) {
                    if (is_numeric($rating) && (int) $rating >= 1 && (int) $rating <= 5) {
                        $weight = (float) collect($criterionWeights)->firstWhere('criterion_id', $criterionId)['weight'];
                        $weightedScores[$criterionId] = round(((int) $rating / 5) * $weight, 2);
                    }
                }
                $calculation = ['weighted_scores' => $weightedScores, 'overall_score' => null];
                $classification = null;
            }
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        DB::transaction(function () use ($record, $criteria, $data, $ratings, $calculation, $classification, $submit, $request) {
            foreach ($criteria as $criterion) {
                $incoming = $data['ratings'][(string) $criterion->id] ?? [];
                DB::table('hrm_kpi_evaluation_ratings')->where('id', $criterion->id)->update([
                    'rating' => $ratings[(string) $criterion->id], 'weighted_score' => $calculation['weighted_scores'][(string) $criterion->id],
                    'comments' => $incoming['comments'] ?? null, 'updated_at' => now(),
                ]);
            }
            $from = $record->status;
            $to = $submit ? 'Submitted' : 'In Progress';
            DB::table('hrm_kpi_evaluations')->where('id', $record->id)->update([
                'overall_score' => $calculation['overall_score'], 'classification' => $classification,
                'evaluator_comments' => $data['comments'] ?? null, 'strengths' => $data['strengths'] ?? null,
                'development_recommendations' => $data['development_recommendations'] ?? null,
                'recommendation' => $data['recommendation'] ?? null, 'status' => $to,
                'submitted_at' => $submit ? now() : null, 'updated_at' => now(),
            ]);
            $this->audit($record->id, $request->user()->id, $submit ? 'submitted' : 'saved', $from, $to);
        });
        if ($submit && $record->manager_id) {
            Notification::notify((int) $record->manager_id, 'kpi_manager_review', 'KPI evaluation ready for review', "Evaluation {$record->reference} is awaiting your review.", 'hrm.workforce.performance-kpi.index', $request->user()->id);
        }
        return response()->json(['message' => $submit ? 'Submitted for manager review.' : 'Draft saved.']);
    }

    public function cancel(Request $request, int $evaluation)
    {
        $this->assertHr($request);
        DB::transaction(function () use ($evaluation, $request) {
            $record = DB::table('hrm_kpi_evaluations')->where('id', $evaluation)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($record->status, ['Draft', 'Assigned'], true), 422, 'Only an unstarted evaluation can be cancelled.');
            DB::table('hrm_kpi_evaluations')->where('id', $record->id)->update(['status' => 'Cancelled', 'updated_at' => now()]);
            $this->audit($record->id, $request->user()->id, 'cancelled', $record->status, 'Cancelled');
        });
        return response()->json(['message' => 'Evaluation cancelled.']);
    }

    public function managerReview(Request $request, int $evaluation)
    {
        $record = DB::table('hrm_kpi_evaluations')->where('id', $evaluation)->firstOrFail();
        abort_unless((int) $record->manager_id === (int) $request->user()->id, 403, 'Only the assigned reviewing manager can review this evaluation.');
        abort_unless($record->status === 'Submitted', 422, 'Only submitted evaluations can be reviewed.');
        $data = $request->validate(['decision' => 'required|in:Approve,Return,Request Evidence', 'comments' => 'nullable|string|max:10000']);
        $to = $data['decision'] === 'Approve' ? 'HR Review' : 'Returned for Revision';
        DB::transaction(function () use ($record, $data, $to, $request) {
            DB::table('hrm_kpi_evaluation_reviews')->insert([
                'evaluation_id' => $record->id, 'reviewer_id' => $request->user()->id, 'review_role' => 'manager',
                'decision' => $data['decision'], 'comments' => $data['comments'] ?? null,
                'previous_status' => $record->status, 'new_status' => $to, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('hrm_kpi_evaluations')->where('id', $record->id)->update(['manager_reviewer_id' => $request->user()->id, 'manager_comments' => $data['comments'] ?? null, 'status' => $to, 'updated_at' => now()]);
            $this->audit($record->id, $request->user()->id, 'manager_review', $record->status, $to, ['decision' => $data['decision']]);
        });
        if ($to === 'HR Review') {
            User::query()->where('role', 'HRM')->where('is_active', true)->pluck('id')->each(fn ($id) => Notification::notify((int) $id, 'kpi_hr_review', 'KPI evaluation requires HR review', "Evaluation {$record->reference} is ready for HR review.", 'hrm.workforce.performance-kpi.index', $request->user()->id));
        }
        return response()->json(['message' => 'Manager review recorded.', 'status' => $to]);
    }

    public function hrReview(Request $request, int $evaluation)
    {
        $this->assertHr($request);
        $record = DB::table('hrm_kpi_evaluations')->where('id', $evaluation)->firstOrFail();
        abort_unless($record->status === 'HR Review', 422, 'Manager approval is required before HR can finalize this evaluation.');
        abort_unless((int) $record->evaluator_id !== (int) $request->user()->id, 403, 'An evaluator cannot finalize their own evaluation.');
        $data = $request->validate([
            'decision' => 'required|in:Finalize,Return', 'comments' => 'required|string|min:3|max:10000',
            'evidence_reviewed' => 'required|accepted', 'score_verified' => 'required|accepted',
        ]);
        $ratings = DB::table('hrm_kpi_evaluation_ratings')->where('evaluation_id', $record->id)->get();
        abort_if($ratings->isEmpty() || $ratings->contains(fn ($rating) => $rating->rating === null || ! trim((string) $rating->comments)), 422, 'All KPI criteria must be rated and commented before HR final review.');
        abort_if(! trim((string) $record->evaluator_comments), 422, 'Evaluator comments are required before HR final review.');
        $to = $data['decision'] === 'Finalize' ? 'Completed' : 'Returned for Revision';
        DB::transaction(function () use ($record, $data, $to, $request) {
            DB::table('hrm_kpi_evaluation_reviews')->insert([
                'evaluation_id' => $record->id, 'reviewer_id' => $request->user()->id, 'review_role' => 'hr',
                'decision' => $data['decision'], 'comments' => $data['comments'] ?? null,
                'previous_status' => $record->status, 'new_status' => $to, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('hrm_kpi_evaluations')->where('id', $record->id)->update([
                'hr_reviewer_id' => $request->user()->id, 'hr_comments' => $data['comments'] ?? null, 'status' => $to,
                'completed_at' => $to === 'Completed' ? now() : null, 'updated_at' => now(),
            ]);
            $this->audit($record->id, $request->user()->id, 'hr_review', $record->status, $to, [
                'decision' => $data['decision'], 'evidence_reviewed' => true, 'score_verified' => true,
            ]);
        });
        if ($to === 'Completed') {
            Notification::notify((int) $record->user_id, 'kpi_evaluation_finalized', 'Performance evaluation finalized', "Your evaluation {$record->reference} is available to review.", 'hrm.workforce.performance-kpi.index', $request->user()->id);
        }
        return response()->json(['message' => 'HR review recorded.', 'status' => $to]);
    }

    public function acknowledge(Request $request, int $evaluation)
    {
        $record = DB::table('hrm_kpi_evaluations')->where('id', $evaluation)->firstOrFail();
        abort_unless((int) $record->user_id === (int) $request->user()->id, 403, 'Employees may only acknowledge their own evaluation.');
        abort_unless($record->status === 'Completed', 422, 'Only finalized evaluations can be acknowledged.');
        $data = $request->validate(['agrees' => 'nullable|boolean', 'comments' => 'nullable|string|max:10000']);
        DB::transaction(function () use ($record, $data, $request) {
            DB::table('hrm_kpi_employee_acknowledgments')->updateOrInsert(['evaluation_id' => $record->id], [
                'employee_id' => $request->user()->id, 'agrees' => $data['agrees'] ?? null, 'comments' => $data['comments'] ?? null,
                'acknowledged_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('hrm_kpi_evaluations')->where('id', $record->id)->update(['acknowledged_at' => now(), 'employee_feedback' => $data['comments'] ?? null, 'updated_at' => now()]);
            $this->audit($record->id, $request->user()->id, 'employee_acknowledged', 'Completed', 'Completed', ['agrees' => $data['agrees'] ?? null]);
        });
        return response()->json(['message' => 'Receipt acknowledged. Acknowledgment does not mean agreement.']);
    }

    public function saveTemplate(Request $request)
    {
        $this->assertHr($request);
        $data = $request->validate([
            'id' => 'nullable|integer|exists:hrm_kpi_templates,id', 'name' => 'required|string|max:160',
            'department_id' => 'required|exists:hrm_departments,id',
            'position_id' => ['required', 'integer', Rule::exists('hrm_positions', 'id')->where(fn ($query) => $query->where('department_id', $request->input('department_id')))],
            'description' => 'nullable|string|max:5000', 'frequency' => 'required|in:quarterly,semiannual,annual',
            'effective_date' => 'required|date|after_or_equal:today', 'status' => 'required|in:draft,active,archived',
            'rating_scale' => 'required|array|size:5', 'rating_scale.1' => 'required|string|max:80', 'rating_scale.2' => 'required|string|max:80',
            'rating_scale.3' => 'required|string|max:80', 'rating_scale.4' => 'required|string|max:80', 'rating_scale.5' => 'required|string|max:80',
            'criteria' => 'required|array|min:1', 'criteria.*.category' => 'required|string|max:120',
            'criteria.*.name' => 'required|string|max:180', 'criteria.*.description' => 'required|string|max:5000',
            'criteria.*.weight' => 'required|numeric|min:0.01|max:100', 'criteria.*.performance_standards' => 'required|string|max:5000',
            'criteria.*.measurement_method' => 'nullable|string|max:255', 'criteria.*.required_evidence' => 'nullable|string|max:2000', 'criteria.*.rating_guidelines' => 'nullable|string|max:5000',
        ]);
        try {
            $this->scoring->validateWeights($data['criteria']);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        $templateId = DB::transaction(function () use ($data, $request) {
            $old = isset($data['id']) ? DB::table('hrm_kpi_templates')->where('id', $data['id'])->first() : null;
            $templateCode = $old?->template_code ?: ('KPI-' . Str::upper(Str::random(8)));
            if ($data['status'] === 'active') {
                $duplicate = DB::table('hrm_kpi_templates')->where('status', 'active')->where('evaluation_type', 'regular')
                    ->where('position_id', $data['position_id'])->where('frequency', $data['frequency'])
                    ->where('department_id', $data['department_id'] ?? null)->where('template_code', '!=', $templateCode)->exists();
                abort_if($duplicate, 422, 'An active template already exists for this position and frequency.');
            }
            if ($data['status'] === 'active') {
                DB::table('hrm_kpi_templates')->where('template_code', $templateCode)->where('status', 'active')->update(['status' => 'archived', 'updated_at' => now()]);
            }
            $templateId = DB::table('hrm_kpi_templates')->insertGetId([
                'template_code' => $templateCode,
                'name' => $data['name'], 'department_id' => $data['department_id'] ?? null, 'position_id' => $data['position_id'],
                'evaluation_type' => 'regular', 'description' => $data['description'] ?? null, 'frequency' => $data['frequency'],
                'effective_date' => $data['effective_date'] ?? null, 'version' => $old ? ((int) $old->version + 1) : 1,
                'rating_scale' => json_encode($data['rating_scale']),
                'status' => $data['status'], 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($data['criteria'] as $order => $criterion) {
                DB::table('hrm_kpi_criteria')->insert([
                    'template_id' => $templateId, 'category' => $criterion['category'], 'name' => $criterion['name'],
                    'description' => $criterion['description'] ?? null, 'measurement_method' => $criterion['measurement_method'] ?? null,
                    'weight' => $criterion['weight'], 'performance_standards' => $criterion['performance_standards'],
                    'required_evidence' => $criterion['required_evidence'] ?? null,
                    'rating_guidelines' => isset($criterion['rating_guidelines']) ? json_encode($criterion['rating_guidelines']) : null, 'sort_order' => $order,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $templateId;
        });
        return response()->json(['message' => 'Template version saved.', 'template_id' => $templateId]);
    }

    public function templateAction(Request $request, int $template)
    {
        $this->assertHr($request);
        $record = DB::table('hrm_kpi_templates')->where('id', $template)->where('evaluation_type', 'regular')->firstOrFail();
        $data = $request->validate(['action' => 'required|in:activate,deactivate,archive,duplicate']);
        if ($data['action'] === 'duplicate') {
            $criteria = DB::table('hrm_kpi_criteria')->where('template_id', $record->id)->orderBy('sort_order')->get();
            return response()->json(['template' => ['id' => null, 'name' => $record->name . ' Copy', 'department_id' => $record->department_id, 'position_id' => $record->position_id, 'description' => $record->description, 'frequency' => $record->frequency, 'effective_date' => $record->effective_date, 'status' => 'draft', 'criteria' => $criteria]]);
        }
        if ($data['action'] === 'activate') {
            $criteria = DB::table('hrm_kpi_criteria')->where('template_id', $record->id)->get()->map(fn ($item) => (array) $item)->all();
            try { $this->scoring->validateWeights($criteria); } catch (InvalidArgumentException $e) { return response()->json(['message' => $e->getMessage()], 422); }
            abort_if(DB::table('hrm_kpi_templates')->where('status', 'active')->where('position_id', $record->position_id)->where('frequency', $record->frequency)->where('department_id', $record->department_id)->when($record->template_code, fn ($q) => $q->where('template_code', '!=', $record->template_code), fn ($q) => $q->where('id', '!=', $record->id))->exists(), 422, 'An active template already exists for this position and frequency.');
        }
        $status = match ($data['action']) { 'activate' => 'active', 'deactivate' => 'draft', default => 'archived' };
        DB::transaction(function () use ($record, $status) {
            if ($status === 'active') DB::table('hrm_kpi_templates')->where('status', 'active')->where('id', '!=', $record->id)->when($record->template_code, fn ($q) => $q->where('template_code', $record->template_code), fn ($q) => $q->whereRaw('1 = 0'))->update(['status' => 'archived', 'updated_at' => now()]);
            DB::table('hrm_kpi_templates')->where('id', $record->id)->update(['status' => $status, 'updated_at' => now()]);
        });
        return response()->json(['message' => "Template {$status}."]);
    }

    public function saveClassifications(Request $request)
    {
        $this->assertHr($request);
        $data = $request->validate(['rules' => 'required|array|min:1', 'rules.*.minimum_score' => 'required|numeric|min:0|max:100', 'rules.*.maximum_score' => 'required|numeric|min:0|max:100', 'rules.*.label' => 'required|string|max:60']);
        try { $this->scoring->validateClassifications($data['rules']); } catch (InvalidArgumentException $e) { return response()->json(['message' => $e->getMessage()], 422); }
        DB::transaction(function () use ($data) {
            $version = (int) DB::table('hrm_kpi_classification_rules')->max('version') + 1;
            DB::table('hrm_kpi_classification_rules')->where('active', true)->update(['active' => false, 'updated_at' => now()]);
            foreach ($data['rules'] as $rule) DB::table('hrm_kpi_classification_rules')->insert(['version' => $version, 'minimum_score' => $rule['minimum_score'], 'maximum_score' => $rule['maximum_score'], 'label' => $rule['label'], 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        });
        return response()->json(['message' => 'Classification settings saved as a new version.']);
    }

    public function storePlan(Request $request)
    {
        abort_unless($this->isHr($request->user()) || ($request->user()->position ?? null) === 'manager', 403, 'Only HR or an authorized manager can create a performance plan.');
        $data = $request->validate([
            'user_id' => 'required|exists:users,id', 'evaluation_id' => 'nullable|exists:hrm_kpi_evaluations,id',
            'supervisor_id' => 'nullable|exists:users,id', 'performance_gap' => 'required|string|max:10000',
            'improvement_objective' => 'required|string|max:10000', 'required_actions' => 'required|string|max:10000',
            'training_or_coaching' => 'nullable|string|max:5000', 'success_measurement' => 'required|string|max:5000',
            'notes' => 'nullable|string|max:5000', 'start_date' => 'required|date', 'target_date' => 'required|date|after_or_equal:start_date', 'review_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:Draft,Active',
        ]);
        $employee = $this->eligibleEmployees()->findOrFail($data['user_id']);
        abort_unless($this->isHr($request->user()) || (int) $employee->hrm_department_id === (int) $request->user()->hrm_department_id, 403, 'Managers may only create plans in their department.');
        $id = DB::table('hrm_kpi_pips')->insertGetId($data + ['created_by' => $request->user()->id, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()]);
        if (! empty($data['supervisor_id'])) Notification::notify((int) $data['supervisor_id'], 'kpi_pip_assigned', 'Performance plan assigned', "A performance plan was assigned for {$employee->name}.", 'hrm.workforce.performance-kpi.index', $request->user()->id);
        return response()->json(['id' => $id], 201);
    }

    public function updatePlan(Request $request, int $pip)
    {
        $record = DB::table('hrm_kpi_pips')->where('id', $pip)->firstOrFail();
        abort_unless($this->isHr($request->user()) || (int) $record->supervisor_id === (int) $request->user()->id, 403);
        $data = $request->validate(['status' => 'nullable|in:Draft,Active,Monitoring,Completed,Unsuccessful,Cancelled', 'progress_note' => 'nullable|string|max:5000', 'review_results' => 'nullable|string|max:10000']);
        DB::transaction(function () use ($record, $data, $request) {
            $updates = array_filter(['status' => $data['status'] ?? null, 'review_results' => $data['review_results'] ?? null]);
            if ($updates) DB::table('hrm_kpi_pips')->where('id', $record->id)->update($updates + ['updated_at' => now()]);
            if (! empty($data['progress_note'])) DB::table('hrm_kpi_pip_progress')->insert(['pip_id' => $record->id, 'user_id' => $request->user()->id, 'progress_note' => $data['progress_note'], 'recorded_on' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        });
        $employeeName = User::whereKey($record->user_id)->value('name') ?? 'the employee';
        foreach (array_unique(array_filter([(int) $record->created_by, (int) $record->supervisor_id, (int) $record->user_id])) as $recipientId) {
            if ($recipientId !== (int) $request->user()->id) {
                Notification::notify($recipientId, 'kpi_pip_updated', 'Performance plan updated', "A performance improvement plan for {$employeeName} was updated.", 'hrm.workforce.performance-kpi.index', $request->user()->id);
            }
        }
        return response()->json(['message' => 'Improvement plan updated.']);
    }

    public function uploadEvidence(Request $request, int $evaluation)
    {
        $record = DB::table('hrm_kpi_evaluations')->where('id', $evaluation)->firstOrFail();
        abort_unless((int) $record->evaluator_id === (int) $request->user()->id, 403);
        abort_unless(in_array($record->status, ['Assigned', 'In Progress', 'Returned for Revision'], true), 422);
        $data = $request->validate(['rating_id' => 'required|exists:hrm_kpi_evaluation_ratings,id', 'files' => 'required|array|min:1', 'files.*' => 'file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,csv']);
        abort_unless(DB::table('hrm_kpi_evaluation_ratings')->where('id', $data['rating_id'])->where('evaluation_id', $record->id)->exists(), 422);
        $uploadedEvidence = [];
        foreach ($request->file('files') as $file) {
            $path = $file->store("kpi-evidence/{$record->reference}", 'local');
            $evidenceId = DB::table('hrm_kpi_evaluation_evidence')->insertGetId(['evaluation_rating_id' => $data['rating_id'], 'uploaded_by' => $request->user()->id, 'original_name' => $file->getClientOriginalName(), 'file_path' => $path, 'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(), 'created_at' => now(), 'updated_at' => now()]);
            $uploadedEvidence[] = ['id' => $evidenceId, 'original_name' => $file->getClientOriginalName()];
        }
        return response()->json(['message' => 'Evidence uploaded.', 'evidence' => $uploadedEvidence]);
    }

    public function downloadEvidence(Request $request, int $evaluation, int $evidence)
    {
        $record = DB::table('hrm_kpi_evaluations')->where('id', $evaluation)->firstOrFail();
        abort_unless($this->canViewEvaluation($request->user(), $record), 403);
        $file = DB::table('hrm_kpi_evaluation_evidence as e')->join('hrm_kpi_evaluation_ratings as r', 'r.id', '=', 'e.evaluation_rating_id')
            ->where('e.id', $evidence)->where('r.evaluation_id', $record->id)->firstOrFail();
        return Storage::disk('local')->download($file->file_path, $file->original_name);
    }

    public function export(Request $request)
    {
        $this->assertViewAccess($request);
        $rows = $this->evaluationQuery($request->user(), $this->isHr($request->user()))->orderByDesc('kpi.period_end')->limit(5000)->get();
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Employee ID', 'Employee', 'Department', 'Position', 'KPI Template', 'Version', 'Period Start', 'Period End', 'Score', 'Classification', 'Evaluator', 'Finalized By', 'Status', 'Evaluator Comments', 'Manager Comments', 'HR Comments']);
            foreach ($rows as $row) fputcsv($out, [$row->reference, $row->employee_id, $row->employee_name, $row->department_name, $row->position_name, $row->template_name, $row->template_version, $row->period_start, $row->period_end, $row->overall_score, $row->classification, $row->evaluator_name, $row->hr_reviewer_name, $row->status, $row->evaluator_comments, $row->manager_comments, $row->hr_comments]);
            fclose($out);
        }, 'kpi-performance-report.csv', ['Content-Type' => 'text/csv']);
    }

    private function evaluationQuery(User $user, bool $isHr)
    {
        return DB::table('hrm_kpi_evaluations as kpi')
            ->join('users as employee', 'employee.id', '=', 'kpi.user_id')
            ->leftJoin('hrm_departments as department', 'department.id', '=', 'employee.hrm_department_id')
            ->leftJoin('hrm_positions as position', 'position.id', '=', 'employee.hrm_position_id')
            ->leftJoin('hrm_employment_types as employment_type', 'employment_type.id', '=', 'employee.hrm_employment_type_id')
            ->leftJoin('hrm_kpi_templates as template', 'template.id', '=', 'kpi.template_id')
            ->leftJoin('users as evaluator', 'evaluator.id', '=', 'kpi.evaluator_id')
            ->leftJoin('users as manager', 'manager.id', '=', 'kpi.manager_id')
            ->leftJoin('users as hr_reviewer', 'hr_reviewer.id', '=', 'kpi.hr_reviewer_id')
            ->select('kpi.*', 'employee.name as employee_name', 'employee.employee_id', 'employee.hrm_department_id', 'employee.hrm_position_id', 'employee.hrm_employment_type_id', 'department.name as department_name', 'position.name as position_name', 'employment_type.name as employment_type_name', 'template.name as template_name', 'evaluator.name as evaluator_name', 'manager.name as manager_name', 'hr_reviewer.name as hr_reviewer_name')
            ->when(! $isHr, fn ($query) => $query->where(fn ($scope) => $scope->where('kpi.evaluator_id', $user->id)->orWhere('kpi.manager_id', $user->id)->orWhere(fn ($own) => $own->where('kpi.user_id', $user->id)->where('kpi.status', 'Completed'))));
    }

    private function eligibleEmployees()
    {
        return User::query()->with(['hrmDepartment:id,name', 'hrmOrgPosition:id,name', 'hrmEmploymentType:id,name'])
            ->whereNull('worker_id')->where('is_active', true)
            ->whereDoesntHave('hrmEmploymentType', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%probation%']));
    }

    private function canViewEvaluation(User $user, object $evaluation): bool
    {
        return $this->isHr($user)
            || (in_array($evaluation->status, ['Completed'], true) && (int) $evaluation->user_id === (int) $user->id)
            || (int) $evaluation->evaluator_id === (int) $user->id
            || (int) $evaluation->manager_id === (int) $user->id;
    }

    private function isHr(?User $user): bool
    {
        return strtoupper((string) $user?->role) === 'HRM';
    }

    private function assertHr(Request $request): void
    {
        abort_unless($this->isHr($request->user()), 403, 'Only authorized HR users can manage KPI performance.');
    }

    private function assertViewAccess(Request $request): void
    {
        $hasScopedRecords = DB::table('hrm_kpi_evaluations')->where(function ($query) use ($request) {
            $query->where('evaluator_id', $request->user()->id)
                ->orWhere('manager_id', $request->user()->id)
                ->orWhere(fn ($own) => $own->where('user_id', $request->user()->id)->where('status', 'Completed'));
        })->exists();
        abort_unless($this->isHr($request->user()) || $hasScopedRecords, 403);
    }

    private function audit(int $evaluationId, ?int $actorId, string $action, ?string $from, ?string $to, array $details = []): void
    {
        DB::table('hrm_kpi_audit_logs')->insert(['evaluation_id' => $evaluationId, 'actor_id' => $actorId, 'action' => $action, 'previous_status' => $from, 'new_status' => $to, 'details' => json_encode($details), 'created_at' => now(), 'updated_at' => now()]);
    }
}
