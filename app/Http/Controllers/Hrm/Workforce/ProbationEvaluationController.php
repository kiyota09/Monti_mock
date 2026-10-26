<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\HrmEmploymentType;
use App\Models\Hrm\ProbationEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProbationEvaluationController extends Controller
{
    public const CRITERIA = [
        'quality' => 'Quality of Work',
        'productivity' => 'Productivity',
        'attendance' => 'Attendance and Punctuality',
        'communication' => 'Communication',
        'teamwork' => 'Teamwork',
        'job_knowledge' => 'Job Knowledge',
    ];

    public function index()
    {
        $this->ensureHrControl(request());
        $employees = User::query()->with(['hrmEmploymentType:id,name', 'hrmDepartment:id,name', 'hrmOrgPosition:id,name'])
            ->whereNull('worker_id')->where('is_active', true)
            ->whereHas('hrmEmploymentType', fn ($q) => $q->whereRaw('LOWER(name) = ?', ['probationary']))
            ->orderBy('name')->get(['id', 'name', 'employee_id', 'hrm_department_id', 'hrm_position_id', 'hrm_employment_type_id', 'join_date', 'probation_end_date']);

        $evaluations = ProbationEvaluation::with(['employee:id,name,employee_id,hrm_department_id,hrm_position_id', 'employee.hrmDepartment:id,name', 'employee.hrmOrgPosition:id,name', 'evaluator:id,name', 'reviewer:id,name'])
            ->orderByDesc('period_end')->get();

        return Inertia::render('Dashboard/HRM_NEW/PerformanceKpi', [
            'employees' => $employees,
            'evaluations' => $evaluations,
            'criteria' => self::CRITERIA,
            'metrics' => [
                'employees' => $employees->count(),
                'pending' => $evaluations->whereNotIn('status', ['Finalized'])->count(),
                'forReview' => $evaluations->where('status', 'Submitted')->count(),
                'finalized' => $evaluations->where('status', 'Finalized')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureHrControl($request);
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'period_label' => 'nullable|string|max:80',
            'period_start' => 'required|date', 'period_end' => 'required|date|after_or_equal:period_start',
            'due_date' => 'nullable|date',
        ]);
        $employee = User::with('hrmEmploymentType')->findOrFail($data['user_id']);
        abort_unless(strtolower($employee->hrmEmploymentType?->name ?? '') === 'probationary', 422, 'Choose an active probationary employee.');
        $evaluation = ProbationEvaluation::firstOrCreate(
            ['user_id' => $employee->id, 'period_start' => $data['period_start'], 'period_end' => $data['period_end']],
            ['due_date' => $data['due_date'] ?? $employee->probation_end_date, 'period_label' => $data['period_label'] ?? null, 'evaluator_id' => $request->user()->id, 'status' => 'Draft']
        );
        return response()->json(['evaluation' => $evaluation], $evaluation->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, ProbationEvaluation $evaluation)
    {
        $this->ensureHrControl($request);
        abort_unless($evaluation->status === 'Draft', 422, 'Only draft evaluations can be edited.');
        $data = $request->validate([
            'scores' => 'required|array',
            'scores.*' => 'required|numeric|min:1|max:5',
            'comments' => 'nullable|string|max:10000', 'strengths' => 'nullable|string|max:5000',
            'improvements' => 'nullable|string|max:5000',
            'recommendation' => 'required|in:Regularize,Further review,Do not regularize',
            'submit' => 'sometimes|boolean',
        ]);
        foreach (array_keys(self::CRITERIA) as $criterion) {
            if (! array_key_exists($criterion, $data['scores'])) {
                return response()->json(['message' => 'A score is required for every evaluation criterion.'], 422);
            }
        }
        $score = round(collect($data['scores'])->only(array_keys(self::CRITERIA))->avg() * 20, 2);
        $evaluation->update([
            'scores' => $data['scores'], 'overall_score' => $score,
            'comments' => $data['comments'] ?? null, 'strengths' => $data['strengths'] ?? null,
            'improvements' => $data['improvements'] ?? null, 'recommendation' => $data['recommendation'],
            'status' => ($data['submit'] ?? false) ? 'Submitted' : 'Draft',
        ]);
        return response()->json(['evaluation' => $evaluation->fresh(['employee', 'evaluator', 'reviewer'])]);
    }

    public function review(Request $request, ProbationEvaluation $evaluation)
    {
        $this->ensureHrControl($request);
        abort_unless($evaluation->status === 'Submitted', 422, 'Only submitted evaluations can be reviewed.');
        $data = $request->validate(['decision' => 'required|in:Finalize,Return', 'review_notes' => 'nullable|string|max:10000']);
        DB::transaction(function () use ($request, $evaluation, $data) {
            $changes = [
                'reviewer_id' => $request->user()->id,
                'review_notes' => $data['review_notes'] ?? null,
                'status' => $data['decision'] === 'Finalize' ? 'Finalized' : 'Draft',
            ];

            // Regularization takes effect only after HR finalizes a Regularize recommendation.
            if ($data['decision'] === 'Finalize' && $evaluation->recommendation === 'Regularize') {
                $employee = User::with('hrmEmploymentType')->lockForUpdate()->findOrFail($evaluation->user_id);
                abort_unless(strtolower($employee->hrmEmploymentType?->name ?? '') === 'probationary', 422, 'This employee is no longer probationary.');
                $regularType = HrmEmploymentType::whereRaw('LOWER(name) = ?', ['regular'])
                    ->where('is_active', true)->whereNull('archived_at')->first();
                abort_unless($regularType, 422, 'The active Regular employment type must be configured before regularization.');
                $employee->update([
                    'hrm_employment_type_id' => $regularType->id,
                    'regularization_date' => now()->toDateString(),
                ]);
            }

            $evaluation->update($changes);
        });
        return response()->json(['evaluation' => $evaluation->fresh(['employee', 'evaluator', 'reviewer'])]);
    }

    private function ensureHrControl(Request $request): void
    {
        abort_unless(strtoupper((string) $request->user()?->role) === 'HRM', 403, 'Probation evaluations are controlled by HR.');
    }
}
