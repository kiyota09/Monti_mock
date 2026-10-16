<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\HrmShift;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        $q = HrmShift::withCount('employees');
        if ($s = trim((string) $request->get('search', ''))) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
        }
        if ($request->filled('status')) {
            $q->where('is_active', $request->get('status') === 'Active');
        }
        if ($request->filled('type')) {
            $q->where('shift_type', $request->get('type'));
        }

        $shifts = $q->orderBy('code')->get()->map(fn ($sh) => $this->row($sh))->values();

        $assigned = (int) \DB::table('hrm_shift_user')->distinct('user_id')->count('user_id');

        // Employee roster: one row per user with their current (first) shift.
        // Powers the main table: name / department / position / shift / action.
        $raw = \DB::table('users as u')
            ->leftJoin('hrm_departments as d', 'd.id', '=', 'u.hrm_department_id')
            ->leftJoin('hrm_positions as p', 'p.id', '=', 'u.hrm_position_id')
            ->leftJoin('hrm_shift_user as su', 'su.user_id', '=', 'u.id')
            ->leftJoin('hrm_shifts as s', 's.id', '=', 'su.shift_id')
            ->select(
                'u.id', 'u.name', 'u.email', 'u.employee_id', 'u.position', 'u.department', 'u.role',
                'u.hrm_department_id', 'u.hrm_position_id',
                'u.manufacturing_role', 'u.is_manufacturing_supervisor',
                'd.name as dept_name', 'p.name as pos_name',
                's.id as shift_id', 's.code as shift_code', 's.name as shift_name',
                's.start_time as shift_start', 's.end_time as shift_end',
                'su.effective_date as shift_effective_date'
            )
            ->orderBy('u.name')
            ->take(1000)
            ->get();

        $seen = [];
        $roster = [];
        foreach ($raw as $r) {
            if (isset($seen[$r->id])) {
                // Keep the first assigned shift; skip extra pivot rows.
                if ($seen[$r->id]['shift_id'] === null && $r->shift_id !== null) {
                    $seen[$r->id]['shift_id'] = (int) $r->shift_id;
                    $seen[$r->id]['shift_code'] = $r->shift_code;
                    $seen[$r->id]['shift_name'] = $r->shift_name;
                    $seen[$r->id]['start_time'] = $r->shift_start ?: '—';
                    $seen[$r->id]['end_time'] = $r->shift_end ?: '—';
                    $seen[$r->id]['effective_date'] = $r->shift_effective_date ? substr((string) $r->shift_effective_date, 0, 10) : null;
                }
                continue;
            }
            $seen[$r->id] = [
                'id' => (int) $r->id,
                'name' => $r->name,
                'email' => $r->email,
                'employee_id' => $r->employee_id ?: ('EMP-' . str_pad((string) $r->id, 4, '0', STR_PAD_LEFT)),
                'department' => $r->dept_name ?? $r->department ?? $r->role ?? '—',
                'department_id' => $r->hrm_department_id,
                'position' => $r->pos_name, // resolved to a real title below
                'org_position' => $r->pos_name,
                'rank' => $r->position,
                'manufacturing_role' => $r->manufacturing_role,
                'is_supervisor' => (bool) $r->is_manufacturing_supervisor,
                'shift_id' => $r->shift_id !== null ? (int) $r->shift_id : null,
                'shift_code' => $r->shift_code,
                'shift_name' => $r->shift_name,
                'start_time' => $r->shift_start ?: '—',
                'end_time' => $r->shift_end ?: '—',
                'effective_date' => $r->shift_effective_date ? substr((string) $r->shift_effective_date, 0, 10) : null,
            ];
        }

        // Job title chain (real titles like HR Staff / Operator — never the
        // system rank words like Manager/Staff):
        //   org position → applied org position → position_applied text →
        //   job posting title → onboarding position → manufacturing role →
        //   non-rank legacy text → '—'.
        $emails = array_values(array_filter(array_unique(array_map(
            fn ($row) => strtolower(trim((string) ($row['email'] ?? ''))), $seen
        ))));
        $appliedByEmail = [];
        $onboardByEmail = [];
        if ($emails) {
            $apps = \App\Models\Hrm\Applicant::with(['jobPosting:id,title', 'orgPosition:id,name'])
                ->whereIn('email', $emails)
                ->orderByDesc('id')
                ->get(['id', 'email', 'position_applied', 'job_posting_id', 'org_position_id']);
            foreach ($apps as $a) {
                $key = strtolower(trim((string) $a->email));
                if ($key === '' || isset($appliedByEmail[$key])) {
                    continue; // latest row per email wins (ordered desc)
                }
                $appliedByEmail[$key] = trim((string) (
                    $a->orgPosition?->name ?: $a->position_applied ?: $a->jobPosting?->title ?: ''
                ));
            }
            $missing = array_values(array_filter(
                $emails, fn ($e) => empty($appliedByEmail[$e])
            ));
            if ($missing) {
                $obs = \App\Models\Hrm\HrmOnboarding::whereIn('email', $missing)
                    ->orderByDesc('id')
                    ->get(['id', 'email', 'position']);
                foreach ($obs as $o) {
                    $key = strtolower(trim((string) $o->email));
                    if ($key === '' || isset($onboardByEmail[$key])) {
                        continue;
                    }
                    $onboardByEmail[$key] = trim((string) ($o->position ?: ''));
                }
            }
        }
        foreach ($seen as $id => $row) {
            $key = strtolower(trim((string) ($row['email'] ?? '')));
            $seen[$id]['position'] = $this->jobTitle(
                $row['org_position'] ?? null,
                ($key !== '' && ! empty($appliedByEmail[$key])) ? $appliedByEmail[$key] : null,
                ($key !== '' && ! empty($onboardByEmail[$key])) ? $onboardByEmail[$key] : null,
                $row['manufacturing_role'] ?? null,
                (bool) ($row['is_supervisor'] ?? false),
                $row['rank'] ?? null
            );
            unset($seen[$id]['email'], $seen[$id]['org_position'], $seen[$id]['rank'], $seen[$id]['manufacturing_role'], $seen[$id]['is_supervisor']);
        }
        $roster = array_values($seen);

        $departments = \App\Models\Hrm\HrmDepartment::whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values()
            ->all();

        return Inertia::render('Dashboard/HRM_NEW/Shift', [
            'shifts' => $shifts,
            'roster' => $roster,
            'departments' => $departments,
            'summary' => [
                'total' => HrmShift::count(),
                'active' => HrmShift::where('is_active', true)->count(),
                'inactive' => HrmShift::where('is_active', false)->count(),
                'assigned' => $assigned,
            ],
            'filters' => $request->only(['search', 'status', 'type']),
            'types' => HrmShift::TYPES,
            'days' => HrmShift::DAYS,
            'nextCode' => HrmShift::nextCode(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function show(HrmShift $shift)
    {
        $shift->load(['employees' => fn ($q) => $q->select('users.id', 'users.name', 'users.email', 'users.employee_id', 'users.position', 'users.department', 'users.role', 'users.work_schedule', 'users.is_active', 'users.hrm_position_id', 'users.hrm_department_id', 'users.manufacturing_role', 'users.is_manufacturing_supervisor')->with(['hrmOrgPosition:id,name', 'hrmDepartment:id,name'])]);

        // Same job-title chain as the roster.
        $appliedByEmail = [];
        $onboardByEmail = [];
        $emails = $shift->employees->map(fn ($u) => strtolower(trim((string) $u->email)))->filter()->unique()->values()->all();
        if ($emails) {
            $apps = \App\Models\Hrm\Applicant::with(['jobPosting:id,title', 'orgPosition:id,name'])
                ->whereIn('email', $emails)
                ->orderByDesc('id')
                ->get(['id', 'email', 'position_applied', 'job_posting_id', 'org_position_id']);
            foreach ($apps as $a) {
                $key = strtolower(trim((string) $a->email));
                if ($key === '' || isset($appliedByEmail[$key])) {
                    continue;
                }
                $appliedByEmail[$key] = trim((string) (
                    $a->orgPosition?->name ?: $a->position_applied ?: $a->jobPosting?->title ?: ''
                ));
            }
            $missing = array_values(array_filter($emails, fn ($e) => empty($appliedByEmail[$e])));
            if ($missing) {
                $obs = \App\Models\Hrm\HrmOnboarding::whereIn('email', $missing)
                    ->orderByDesc('id')
                    ->get(['id', 'email', 'position']);
                foreach ($obs as $o) {
                    $key = strtolower(trim((string) $o->email));
                    if ($key === '' || isset($onboardByEmail[$key])) {
                        continue;
                    }
                    $onboardByEmail[$key] = trim((string) ($o->position ?: ''));
                }
            }
        }

        return response()->json([
            'shift' => $this->row($shift),
            'employees' => $shift->employees->map(function ($u) use ($appliedByEmail, $onboardByEmail) {
                $key = strtolower(trim((string) $u->email));
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'employee_id' => $u->employee_id ?: ('EMP-' . str_pad((string) $u->id, 4, '0', STR_PAD_LEFT)),
                    'position' => $this->jobTitle(
                        $u->hrmOrgPosition?->name,
                        ($key !== '' && ! empty($appliedByEmail[$key])) ? $appliedByEmail[$key] : null,
                        ($key !== '' && ! empty($onboardByEmail[$key])) ? $onboardByEmail[$key] : null,
                        $u->manufacturing_role,
                        (bool) $u->is_manufacturing_supervisor,
                        $u->position
                    ),
                    'department' => $u->hrmDepartment?->name ?? $u->department ?? $u->role,
                    'work_schedule' => $u->work_schedule ?: '—',
                    'status' => $u->is_active ? 'Active' : 'Inactive',
                ];
            })->values()->all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20|unique:hrm_shifts,code',
            'shift_type' => 'required|in:Fixed,Flexible,Rotating',
            'start_time' => 'nullable|string|max:20',
            'end_time' => 'nullable|string|max:20',
            'break_minutes' => 'nullable|integer|min:0|max:480',
            'grace_minutes' => 'nullable|integer|min:0|max:180',
            'working_days' => 'nullable|array',
            'working_days.*' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'is_active' => 'nullable|boolean',
        ]);

        $shift = HrmShift::create([
            'name' => $data['name'],
            'code' => $data['code'] ?: HrmShift::nextCode(),
            'shift_type' => $data['shift_type'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'break_minutes' => (int) ($data['break_minutes'] ?? 60),
            'grace_minutes' => (int) ($data['grace_minutes'] ?? 15),
            'working_days' => $data['working_days'] ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return back()->with('message', "Shift {$shift->code} created.");
    }

    public function update(Request $request, HrmShift $shift)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:20|unique:hrm_shifts,code,' . $shift->id,
            'shift_type' => 'sometimes|in:Fixed,Flexible,Rotating',
            'start_time' => 'nullable|string|max:20',
            'end_time' => 'nullable|string|max:20',
            'break_minutes' => 'nullable|integer|min:0|max:480',
            'grace_minutes' => 'nullable|integer|min:0|max:180',
            'working_days' => 'nullable|array',
            'working_days.*' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'is_active' => 'nullable|boolean',
        ]);

        $shift->update(array_filter($data, fn ($v) => $v !== null));

        return back()->with('message', "Shift {$shift->code} updated.");
    }

    public function toggle(HrmShift $shift)
    {
        $shift->update(['is_active' => ! $shift->is_active]);

        return back()->with('message', "Shift {$shift->code} " . ($shift->is_active ? 'activated.' : 'deactivated.'));
    }

    public function assign(Request $request, HrmShift $shift)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'effective_date' => 'nullable|date',
        ]);
        $pivot = [];
        foreach ($data['user_ids'] as $id) {
            $pivot[$id] = ['effective_date' => $data['effective_date'] ?? null];
        }
        $shift->employees()->syncWithoutDetaching($pivot);

        return back()->with('message', count($data['user_ids']) . ' employee(s) assigned to ' . $shift->code . '.');
    }

    public function unassign(HrmShift $shift, User $user)
    {
        $shift->employees()->detach($user->id);

        return back()->with('message', $user->name . ' removed from ' . $shift->code . '.');
    }

    /**
     * Move one employee to a new shift (single-shift roster model).
     * Time always follows the selected shift definition.
     */
    public function reassign(Request $request, User $user)
    {
        $data = $request->validate([
            'shift_id' => 'nullable|exists:hrm_shifts,id',
            'effective_date' => 'nullable|date',
        ]);

        \DB::table('hrm_shift_user')->where('user_id', $user->id)->delete();

        if (! empty($data['shift_id'])) {
            $shift = HrmShift::findOrFail($data['shift_id']);
            $shift->employees()->syncWithoutDetaching([
                $user->id => ['effective_date' => $data['effective_date'] ?? null],
            ]);

            return back()->with('message', $user->name . ' moved to ' . $shift->code . '.');
        }

        return back()->with('message', $user->name . ' unassigned from all shifts.');
    }

    /**
     * Group-assign: move many employees to the same shift at once
     * (driven by the roster checkboxes).
     */
    public function bulkAssign(Request $request)
    {
        $data = $request->validate([
            'shift_id' => 'required|exists:hrm_shifts,id',
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'effective_date' => 'nullable|date',
        ]);

        $shift = HrmShift::findOrFail($data['shift_id']);

        foreach (array_chunk($data['user_ids'], 200) as $chunk) {
            \DB::table('hrm_shift_user')->whereIn('user_id', $chunk)->delete();
            $pivot = [];
            foreach ($chunk as $id) {
                $pivot[$id] = ['effective_date' => $data['effective_date'] ?? null];
            }
            $shift->employees()->syncWithoutDetaching($pivot);
        }

        return back()->with('message', count($data['user_ids']) . ' employee(s) grouped into ' . $shift->code . '.');
    }

    public function employees(Request $request)
    {
        $q = User::select('id', 'name', 'employee_id', 'position', 'department', 'role')
            ->where('is_active', true)
            ->orderBy('name');
        if ($s = trim((string) $request->get('search', ''))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('employee_id', 'like', "%{$s}%"));
        }

        return response()->json([
            'employees' => $q->take(50)->get()->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'employee_id' => $u->employee_id ?: ('EMP-' . str_pad((string) $u->id, 4, '0', STR_PAD_LEFT)),
            ])->values()->all(),
        ]);
    }

    private function row(HrmShift $sh): array
    {
        return [
            'id' => $sh->id,
            'code' => $sh->code,
            'name' => $sh->name,
            'shift_type' => $sh->shift_type,
            'start_time' => $sh->start_time ?: '—',
            'end_time' => $sh->end_time ?: '—',
            'break' => $sh->breakLabel(),
            'break_minutes' => (int) ($sh->break_minutes ?? 0),
            'grace' => $sh->graceLabel(),
            'grace_minutes' => (int) ($sh->grace_minutes ?? 0),
            'working_days' => $sh->working_days ?? [],
            'working_days_label' => $sh->workingDaysLabel(),
            'assigned' => (int) ($sh->employees_count ?? $sh->employees()->count()),
            'status' => $sh->is_active ? 'Active' : 'Inactive',
            'is_active' => (bool) $sh->is_active,
        ];
    }

    /**
     * Resolve a real job title (HR Staff, Operator, …) for the roster.
     * System rank words (manager/staff/…) are never shown — they mean the
     * employee simply has no linked title yet, so '—' is returned instead.
     */
    private function jobTitle(
        ?string $orgPosition,
        ?string $appliedTitle,
        ?string $onboardingPosition,
        ?string $manufacturingRole,
        bool $isSupervisor,
        ?string $rank
    ): string {
        if ($orgPosition && trim($orgPosition) !== '') {
            return trim($orgPosition);
        }
        if ($appliedTitle && trim($appliedTitle) !== '') {
            return trim($appliedTitle);
        }
        if ($onboardingPosition && trim($onboardingPosition) !== '') {
            return trim($onboardingPosition);
        }
        if ($manufacturingRole && trim($manufacturingRole) !== '') {
            $label = ucwords(str_replace(['_', '-'], ' ', strtolower(trim($manufacturingRole))));
            // Canonical seeded titles carry the " Staff" suffix
            // (Knitting Yarn Staff, Dyeing Squeezer Staff, …).
            return str_ends_with($label, ' Staff') ? $label : $label . ' Staff';
        }
        if ($isSupervisor) {
            return 'Manufacturing Supervisor';
        }
        $rank = strtolower(trim((string) $rank));
        $rankWords = ['trainee', 'staff', 'supervisor', 'manager', 'secretary', 'special_officer', 'special officer', 'vice_president', 'vice president', 'admin', 'ceo', 'coo'];
        if ($rank !== '' && ! in_array($rank, $rankWords, true)) {
            return ucfirst($rank);
        }

        return '—';
    }
}
