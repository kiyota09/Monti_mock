<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmPosition;
use App\Support\ManufacturingRoles;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PositionController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        $q = HrmPosition::with(['department', 'reportsTo']);
        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
        }
        if ($d = $request->get('department')) {
            $q->where('department_id', $d);
        }
        if ($request->get('status') === 'archived') {
            $q->whereNotNull('archived_at');
        } else {
            $q->whereNull('archived_at');
        }

        $positions = $q->latest()->paginate(12)->withQueryString();
        // Attach filled counts (employees holding each org position)
        $positions->getCollection()->transform(function ($p) {
            $p->filled_positions = User::where('hrm_position_id', $p->id)->count();
            $p->department_name = $p->department?->name;
            $p->reports_to_name = $p->reportsTo?->name;
            return $p;
        });

        return Inertia::render('Dashboard/HRM_NEW/Position', [
            'positionsData' => $positions,
            'filters' => $request->only(['search', 'department', 'status']),
            'filterOptions' => [
                'departments' => HrmDepartment::select('id', 'name', 'category')->get(),
                'managementLevels' => ['Executive', 'Managerial', 'Supervisory', 'Individual Contributor', 'Trainee'],
            ],
            'metrics' => [
                'active' => HrmPosition::whereNull('archived_at')->count(),
                'archived' => HrmPosition::whereNotNull('archived_at')->count(),
                'totalApproved' => (int) HrmPosition::sum('approved_headcount'),
                'totalFilled' => (int) User::whereNotNull('hrm_position_id')->count(),
                'totalVacant' => max(0, (int) HrmPosition::sum('approved_headcount') - (int) User::whereNotNull('hrm_position_id')->count()),
            ],
            'departments' => HrmDepartment::select('id', 'name', 'category')->get(),
            'allPositions' => HrmPosition::select('id', 'name', 'department_id')->get(),
            'manufacturingRoles' => collect(ManufacturingRoles::LABELS)->map(fn ($label, $slug) => ['value' => $slug, 'label' => $label])->values(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // Blank codes are auto-generated server-side (the UI sends none)
            // so client-side guessing can never collide with existing rows.
            'code' => 'nullable|string|max:30|unique:hrm_positions,code',
            'name' => 'required|string|max:255',
            'department_id' => 'nullable|exists:hrm_departments,id',
            'reports_to_id' => 'nullable|exists:hrm_positions,id',
            'management_level' => 'nullable|string|max:60',
            'organization_level' => 'nullable|integer|min:1|max:5',
            'description' => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'qualifications' => 'nullable|string',
            'required_skills' => 'nullable|string',
        'salary_min' => 'nullable|integer|min:0',
        'salary_max' => 'nullable|integer|min:0|gte:salary_min',
        'overtime_eligible' => 'nullable|boolean',
        'approved_headcount' => 'nullable|integer|min:1',
        'status' => 'nullable|in:active,inactive',
    ]);

        $this->validateManufacturingPosition($request, $data);

        if (empty($data['code'])) {
            $data['code'] = self::nextCode();
        }

        HrmPosition::create($data);

        return back()->with('success', 'Position created.');
    }

    private function validateManufacturingPosition(Request $request, array $data, ?HrmPosition $position = null): void
    {
        $departmentId = $data['department_id'] ?? $position?->department_id;
        $department = $departmentId ? HrmDepartment::find($departmentId) : null;

        if (strtoupper((string) $department?->category) !== 'MAN') {
            return;
        }

        $request->validate([
            'name' => ['required', \Illuminate\Validation\Rule::in(array_values(ManufacturingRoles::LABELS))],
        ]);
    }
    protected static function nextCode(): string
    {
        $next = (int) (HrmPosition::max('id') ?? 0) + 1;
        do {
            $code = 'POS-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (HrmPosition::where('code', $code)->exists());

        return $code;
    }

    public function update(Request $request, HrmPosition $position)
    {
        $data = $request->validate([
            'code' => 'sometimes|string|max:30|unique:hrm_positions,code,' . $position->id,
            'name' => 'sometimes|string|max:255',
            'department_id' => 'nullable|exists:hrm_departments,id',
            'reports_to_id' => 'nullable|exists:hrm_positions,id',
            'management_level' => 'nullable|string|max:60',
            'organization_level' => 'nullable|integer|min:1|max:5',
            'description' => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'qualifications' => 'nullable|string',
            'required_skills' => 'nullable|string',
        'salary_min' => 'nullable|integer|min:0',
        'salary_max' => 'nullable|integer|min:0|gte:salary_min',
        'overtime_eligible' => 'nullable|boolean',
        'approved_headcount' => 'nullable|integer|min:1',
        'status' => 'nullable|in:active,inactive',
    ]);
        // A position can never report to itself — drop the self-reference.
        if (!empty($data['reports_to_id']) && (int) $data['reports_to_id'] === (int) $position->id) {
            $data['reports_to_id'] = null;
        }
        $this->validateManufacturingPosition($request, $data, $position);
        $position->update($data);

        return back()->with('success', 'Position updated.');
    }

    /**
     * Position detail (ported from super_monti; JSON instead of a
     * separate PositionShow page).
     */
    public function show(HrmPosition $position)
    {
        $position->load(['department', 'reportsTo', 'subordinates']);
        $request = request();

        return $request->wantsJson() || $request->expectsJson()
            ? response()->json([
                'id' => $position->id,
                'code' => $position->code,
                'name' => $position->name,
                'department' => $position->department ? ['id' => $position->department->id, 'name' => $position->department->name] : null,
                'reports_to' => $position->reportsTo ? ['id' => $position->reportsTo->id, 'name' => $position->reportsTo->name] : null,
                'subordinates' => $position->subordinates()->select('id', 'name', 'code')->orderBy('name')->get(),
                'management_level' => $position->management_level,
                'organization_level' => $position->organization_level,
                'salary_min' => $position->salary_min,
                'salary_max' => $position->salary_max,
                'approved_headcount' => $position->approved_headcount,
                'filled' => $position->holders()->count(),
                'status' => $position->status,
                'archived_at' => $position->archived_at,
            ])
            : back();
    }

    public function destroy(HrmPosition $position)
    {
        // Blocked while employees hold the position (ported from super_monti).
        if ($position->holders()->count() > 0) {
            return back()->with('error', 'Cannot delete position with active employees.');
        }
        $position->delete();

        return back()->with('success', 'Position deleted.');
    }

    /**
     * Duplicate a position (ported from super_monti, adapted: this schema
     * has no draft status or stored counters, so the copy starts inactive).
     */
    public function duplicate(HrmPosition $position)
    {
        $clone = $position->replicate();
        $baseCode = $position->code . '-COPY';
        $code = $baseCode;
        $suffix = 2;
        while (HrmPosition::where('code', $code)->exists()) {
            $code = $baseCode . '-' . $suffix++;
        }
        $clone->code = $code;
        $clone->name = $position->name . ' (Copy)';
        $clone->status = 'inactive';
        $clone->archived_at = null;
        $clone->save();

        return back()->with('success', 'Position duplicated successfully.');
    }

    /**
     * Filtered CSV export (ported from super_monti, adapted to holders).
     */
    public function export(Request $request)
    {
        $status = $request->get('status');
        $departmentId = $request->get('department');

        return response()->streamDownload(function () use ($status, $departmentId) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['code', 'name', 'department', 'management_level', 'salary_min', 'salary_max', 'headcount', 'filled', 'vacant', 'status']);
            $q = HrmPosition::with('department');
            if ($status === 'archived') {
                $q->whereNotNull('archived_at');
            } else {
                $q->whereNull('archived_at');
            }
            if ($departmentId) {
                $q->where('department_id', $departmentId);
            }
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    $filled = User::where('hrm_position_id', $p->id)->count();
                    fputcsv($out, [
                        $p->code, $p->name, $p->department?->name,
                        $p->management_level, $p->salary_min, $p->salary_max,
                        $p->approved_headcount, $filled,
                        max(0, (int) $p->approved_headcount - $filled),
                        $p->archived_at ? 'archived' : $p->status,
                    ]);
                }
            });
            fclose($out);
        }, 'positions.csv');
    }

    /**
     * Position statistics (ported from super_monti, adapted to holders).
     */
    public function stats()
    {
        $active = HrmPosition::whereNull('archived_at');
        $filledTotal = User::whereNotNull('hrm_position_id')->count();
        $approvedTotal = (int) (clone $active)->sum('approved_headcount');

        return response()->json([
            'total' => (clone $active)->count(),
            'active' => (clone $active)->where('status', 'active')->count(),
            'inactive' => (clone $active)->where('status', 'inactive')->count(),
            'archived' => HrmPosition::whereNotNull('archived_at')->count(),
            'total_headcount' => $approvedTotal,
            'total_filled' => $filledTotal,
            'total_vacant' => max(0, $approvedTotal - $filledTotal),
            'by_department' => (clone $active)
                ->select('department_id', DB::raw('count(*) as count'))
                ->groupBy('department_id')->with('department')->get()
                ->map(fn ($item) => [
                    'department_name' => $item->department?->name ?? 'Unknown',
                    'count' => $item->count,
                ]),
            'by_management_level' => (clone $active)
                ->select('management_level', DB::raw('count(*) as count'))
                ->groupBy('management_level')->get(),
        ]);
    }

    public function archive(HrmPosition $position)
    {
        $position->update(['archived_at' => now(), 'status' => 'inactive']);

        return back()->with('success', 'Position archived.');
    }

    public function reactivate(HrmPosition $position)
    {
        $position->update(['archived_at' => null, 'status' => 'active']);

        return back()->with('success', 'Position reactivated.');
    }

    public function employees(HrmPosition $position)
    {
        $employees = User::where('hrm_position_id', $position->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'employee_id']);

        return response()->json([
            'employees' => $employees,
            'total' => $employees->count(),
        ]);
    }
}
