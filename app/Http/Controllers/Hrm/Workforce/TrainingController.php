<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\HrmCertification;
use App\Models\Hrm\HrmTraining;
use App\Models\Hrm\HrmTrainingEnrollment;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TrainingController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        $q = HrmTraining::withCount('enrollments');
        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%"));
        }
        if ($st = $request->get('status')) {
            $q->where('status', $st);
        }

        $paginated = $q->latest()->paginate(12)->withQueryString();
        $trainings = collect($paginated->items())->filter(fn ($t) => $t !== null)->map(function ($t) {
            $completed = HrmTrainingEnrollment::where('training_id', $t->id)->where('status', 'Completed')->count();
            return [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'status' => $t->status,
                'enrolled' => $t->enrollments_count,
                'completed' => $completed,
                'duration' => $t->duration_hours . ' hours',
                'expiring' => $t->expiry_days !== null && HrmCertification::where('training_id', $t->id)
                    ->whereDate('expiry_date', '<=', now()->addDays(30))->exists(),
            ];
        })->values()->all();

        $totalEnrollments = HrmTrainingEnrollment::count();
        $completedEnrollments = HrmTrainingEnrollment::where('status', 'Completed')->count();

        return Inertia::render('Dashboard/HRM_NEW/Training', [
            'trainings' => $trainings,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'metrics' => [
                ['label' => 'Active Courses', 'value' => (string) HrmTraining::where('status', 'In Progress')->count()],
                ['label' => 'Completed', 'value' => $totalEnrollments > 0 ? (int) round($completedEnrollments / $totalEnrollments * 100) . '%' : '0%'],
                ['label' => 'In Progress', 'value' => (string) HrmTrainingEnrollment::whereIn('status', ['Enrolled', 'In Progress'])->count()],
                ['label' => 'Certifications', 'value' => (string) HrmCertification::count()],
            ],
            'filters' => $request->only(['search', 'status']),
            'employees' => User::where('is_active', true)->orderBy('name')->select('id', 'name')->take(200)->get(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function store(Request $request)
    {
        HrmTraining::create($request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:Draft,In Progress,Completed',
            'duration_hours' => 'nullable|integer|min:1',
            'expiry_days' => 'nullable|integer|min:1',
        ]) + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Course created.');
    }

    public function update(Request $request, HrmTraining $training)
    {
        $training->update($request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:Draft,In Progress,Completed',
            'duration_hours' => 'nullable|integer|min:1',
            'expiry_days' => 'nullable|integer|min:1',
        ]));

        return back()->with('success', 'Course updated.');
    }

    public function destroy(HrmTraining $training)
    {
        $training->delete();

        return back()->with('success', 'Course deleted.');
    }

    public function enroll(Request $request, HrmTraining $training)
    {
        $data = $request->validate(['user_ids' => 'required|array|min:1', 'user_ids.*' => 'exists:users,id']);
        foreach ($data['user_ids'] as $uid) {
            HrmTrainingEnrollment::firstOrCreate(
                ['training_id' => $training->id, 'user_id' => $uid],
                ['status' => 'Enrolled', 'progress' => 0]
            );
        }

        return back()->with('success', 'Employees enrolled.');
    }

    public function enrollmentStatus(Request $request, HrmTraining $training, HrmTrainingEnrollment $enrollment)
    {
        abort_unless($enrollment->training_id === $training->id, 404);
        $data = $request->validate([
            'status' => 'required|in:Enrolled,In Progress,Completed',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);
        $enrollment->update($data + [
            'completed_at' => $data['status'] === 'Completed' ? now() : null,
        ]);
        if ($data['status'] === 'Completed') {
            HrmCertification::firstOrCreate(
                ['user_id' => $enrollment->user_id, 'training_id' => $training->id],
                ['name' => $training->name, 'issued_at' => now()->toDateString(),
                 'expiry_date' => $training->expiry_days ? now()->addDays($training->expiry_days)->toDateString() : null]
            );
        }

        return back()->with('success', 'Enrollment updated.');
    }
}
