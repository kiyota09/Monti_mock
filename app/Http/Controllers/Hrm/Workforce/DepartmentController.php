<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmEmploymentType;
use App\Models\Hrm\HrmPosition;
use App\Models\Hrm\HrmRole;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        $q = HrmDepartment::withCount(['positions as open_positions' => function ($qq) {
            $qq->where('status', 'active');
        }])->with('head');

        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"));
        }
        if ($request->get('status') === 'archived') {
            $q->whereNotNull('archived_at');
        } else {
            $q->whereNull('archived_at');
        }

        $departments = $q->latest()->paginate(12)->withQueryString();
        // Resolve the head's display name (head_id references a user account).
        // Employee headcount per department: canonical FK link first, plus
        // legacy free-text `users.department` rows that have no FK set
        // (queried with whereNull FK so nobody is double-counted).
        $pageDepts = $departments->getCollection();
        $byDeptId = User::whereIn('hrm_department_id', $pageDepts->pluck('id')->all())
            ->selectRaw('hrm_department_id as id, count(*) as c')
            ->groupBy('hrm_department_id')->pluck('c', 'id');
        $byName = User::whereNull('hrm_department_id')->whereIn('department', $pageDepts->pluck('name')->all())
            ->selectRaw('department, count(*) as c')
            ->groupBy('department')->pluck('c', 'department');
        // Resolve who created each department (stored as users.id at creation).
        $creatorNames = User::whereIn('id', $pageDepts->pluck('created_by')->filter()->unique()->all())
            ->pluck('name', 'id');
        $departments->setCollection($pageDepts->map(function ($d) use ($byDeptId, $byName, $creatorNames) {
            $d->setAttribute('head_name', $d->head?->name);
            $d->setAttribute('employee_count', (int) ($byDeptId[$d->id] ?? 0) + (int) ($byName[$d->name] ?? 0));
            $d->setAttribute('created_by_name', $d->created_by ? ($creatorNames[$d->created_by] ?? 'System') : 'System');
            unset($d->head);

            return $d;
        }));

        return Inertia::render('Dashboard/HRM_NEW/Department', [
            'departmentsData' => $departments,
            'filters' => $request->only(['search', 'status']),
            'metrics' => [
                'active' => HrmDepartment::whereNull('archived_at')->count(),
                'archived' => HrmDepartment::whereNotNull('archived_at')->count(),
                'totalEmployees' => User::where('is_active', true)->count(),
                'totalOpenPositions' => HrmPosition::where('status', 'active')->sum('approved_headcount'),
                'totalProjects' => 0,
            ],
            // Department Head picker: manager / supervisor users only, with
            // department ids so the form filters by department.
            // Company-level (NULL department) people show everywhere.
            'headCandidates' => User::with(['hrmOrgPosition:id,name,code', 'hrmDepartment:id,name'])
                ->where('is_active', '!=', 0)
                ->whereIn('position', ['manager', 'supervisor'])
                ->select('id', 'name', 'email', 'role', 'position', 'department', 'hrm_position_id', 'hrm_department_id')
                ->orderBy('name')->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'job_position' => $u->hrmOrgPosition?->name ?: ucfirst((string) $u->position),
                    'department' => $u->hrmDepartment?->name ?: $u->department,
                    'hrm_department_id' => $u->hrm_department_id,
                ])->values()->all(),
            // Active role codes are canonical values shared with users.role.
            'categories' => HrmRole::where('is_active', true)->orderBy('code')->pluck('code')
                ->filter()->unique()->values()->map(fn ($c) => ['value' => $c])->all(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:30|unique:hrm_departments,code',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:60|exists:hrm_roles,code',
            'description' => 'nullable|string',
            'head_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:active,inactive',
        ]);
        $data['created_by'] = $request->user()->id;
        HrmDepartment::create($data);

        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, HrmDepartment $department)
    {
        $data = $request->validate([
            'code' => 'sometimes|string|max:30|unique:hrm_departments,code,' . $department->id,
            'name' => 'sometimes|string|max:255',
            'category' => 'required|string|max:60|exists:hrm_roles,code',
            'description' => 'nullable|string',
            'head_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:active,inactive',
        ]);
        $department->update($data);

        return back()->with('success', 'Department updated.');
    }

    /**
     * Department detail (ported from super_monti; JSON instead of a
     * separate DepartmentShow page).
     */
    public function show(HrmDepartment $department)
    {
        $department->load(['head', 'positions']);
        $request = request();

        return $request->wantsJson() || $request->expectsJson()
            ? response()->json([
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
                'category' => $department->category,
                'description' => $department->description,
                'status' => $department->archived_at ? 'archived' : $department->status,
                'head' => $department->head ? ['id' => $department->head->id, 'name' => $department->head->name] : null,
                'employees_count' => $department->employees()->count(),
                'positions_count' => $department->positions()->count(),
                'positions' => $department->positions()->select('id', 'name', 'code', 'status')->orderBy('name')->get(),
                'archived_at' => $department->archived_at,
                'created_at' => $department->created_at,
                'updated_at' => $department->updated_at,
            ])
            : back();
    }

    /**
     * Delete a department. Blocked while employees are still assigned
     * (ported from super_monti).
     */
    public function destroy(HrmDepartment $department)
    {
        if ($department->employees()->count() > 0) {
            return back()->with('error', 'Cannot delete department with existing employees. Please reassign employees first.');
        }
        $department->delete();

        return back()->with('success', 'Department deleted successfully.');
    }

    /**
     * Dashboard stats (ported from super_monti, adapted to hrm_* schema).
     */
    public function stats()
    {
        return response()->json([
            'total_departments' => HrmDepartment::count(),
            'active_departments' => HrmDepartment::whereNull('archived_at')->count(),
            'archived_departments' => HrmDepartment::whereNotNull('archived_at')->count(),
            'total_employees' => User::where('is_active', true)->count(),
            'total_open_positions' => HrmPosition::where('status', 'active')->sum('approved_headcount'),
            'departments_by_status' => HrmDepartment::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')->get(),
        ]);
    }

    /**
     * Merge source departments into a target department: reassigns all
     * employees (users) and positions, then archives the sources
     * (ported from super_monti, adapted to hrm_* schema).
     */
    public function merge(Request $request)
    {
        $data = $request->validate([
            'target_department_id' => 'required|integer|exists:hrm_departments,id',
            'department_ids' => 'required|array|min:1',
            'department_ids.*' => 'integer|exists:hrm_departments,id',
        ]);

        $target = HrmDepartment::whereNull('archived_at')->findOrFail($data['target_department_id']);
        $sourceIds = array_values(array_filter(
            $data['department_ids'],
            fn ($id) => (int) $id !== (int) $target->id
        ));
        if (empty($sourceIds)) {
            return back()->with('error', 'Select at least one department to merge into the target.');
        }

        DB::transaction(function () use ($target, $sourceIds) {
            $sources = HrmDepartment::whereIn('id', $sourceIds)->get();
            foreach ($sources as $source) {
                $source->employees()->update(['hrm_department_id' => $target->id]);
                $source->positions()->update(['department_id' => $target->id]);
                $source->update(['archived_at' => now(), 'status' => 'inactive']);
            }
        });

        return back()->with('success', count($sourceIds) . ' department(s) merged into ' . $target->name . '.');
    }

    /**
     * Split a department: creates a new department and moves the selected
     * employees (and the positions they hold) over
     * (ported from super_monti, adapted to hrm_* schema).
     */
    public function split(Request $request)
    {
        $data = $request->validate([
            'source_department_id' => 'required|integer|exists:hrm_departments,id',
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:60',
            'description' => 'nullable|string',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:users,id',
        ]);

        $source = HrmDepartment::whereNull('archived_at')->findOrFail($data['source_department_id']);

        $latest = HrmDepartment::orderByDesc('id')->first();
        $nextNumber = $latest ? ((int) filter_var($latest->code, FILTER_SANITIZE_NUMBER_INT) + 1) : 1;

        $created = DB::transaction(function () use ($source, $data, $nextNumber) {
            $department = HrmDepartment::create([
                'code' => 'DEP-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT),
                'name' => $data['name'],
                'category' => $data['category'] ?? $source->category,
                'description' => $data['description'] ?? null,
                'status' => 'active',
                'created_by' => $request->user()->id,
            ]);

            $employeeIds = array_values(array_filter($data['employee_ids'] ?? []));
            if (! empty($employeeIds)) {
                User::whereIn('id', $employeeIds)->update(['hrm_department_id' => $department->id]);
                $movedPositionIds = User::where('hrm_department_id', $department->id)
                    ->whereNotNull('hrm_position_id')
                    ->distinct()->pluck('hrm_position_id');
                if ($movedPositionIds->isNotEmpty()) {
                    HrmPosition::whereIn('id', $movedPositionIds)->update(['department_id' => $department->id]);
                }
            }

            return $department;
        });

        return back()->with('success', 'New department "' . $created->name . '" created from split.');
    }

    public function archive(HrmDepartment $department)
    {
        $department->update(['archived_at' => now(), 'status' => 'inactive']);

        return back()->with('success', 'Department archived.');
    }

    public function reactivate(HrmDepartment $department)
    {
        $department->update(['archived_at' => null, 'status' => 'active']);

        return back()->with('success', 'Department reactivated.');
    }

    public function export(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');

        return response()->streamDownload(function () use ($search, $status) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['code', 'name', 'category', 'description', 'head', 'employees', 'open_positions', 'status', 'created_at']);
            $q = HrmDepartment::with('head')->withCount(['employees', 'positions']);
            if ($search) {
                $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            }
            if ($status === 'archived') {
                $q->whereNotNull('hrm_departments.archived_at');
            } elseif ($status === 'active') {
                $q->whereNull('hrm_departments.archived_at');
            }
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $d) {
                    fputcsv($out, [
                        $d->code, $d->name, $d->category, $d->description,
                        $d->head?->name ?? '', $d->employees_count, $d->positions_count,
                        $d->archived_at ? 'archived' : $d->status,
                        optional($d->created_at)->format('Y-m-d'),
                    ]);
                }
            });
            fclose($out);
        }, 'departments.csv');
    }
}
