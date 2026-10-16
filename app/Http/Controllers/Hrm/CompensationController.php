<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\CompensationComponent;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmEmploymentType;
use App\Models\Hrm\HrmPosition;
use App\Models\Hrm\SalaryAdjustment;
use App\Models\Hrm\SalaryAdjustmentApproval;
use App\Models\Hrm\SalaryHistory;
use App\Support\SalaryAdjustmentApplier;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CompensationController extends Controller
{
    use HasPagePermissions;

    public function index()
    {
        $users = User::with(['hrmDepartment:id,name', 'hrmOrgPosition:id,name', 'hrmEmploymentType:id,name'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $openByUser = SalaryAdjustment::whereIn('status', SalaryAdjustment::OPEN_STATUSES)
            ->selectRaw('user_id, count(*) as c')
            ->groupBy('user_id')
            ->pluck('c', 'user_id');

        $lastApplied = SalaryHistory::selectRaw('user_id, max(effective_date) as d')
            ->groupBy('user_id')
            ->pluck('d', 'user_id');

        $employees = $users->map(fn ($u) => [
            'id' => $u->id,
            'employeeId' => $u->employee_id ?: ('EMP-' . str_pad((string) $u->id, 4, '0', STR_PAD_LEFT)),
            'name' => $u->name,
            'department' => $u->hrmDepartment?->name ?: ($u->department ?: '—'),
            'departmentId' => $u->hrm_department_id,
            'position' => $u->hrmOrgPosition?->name ?: ucfirst((string) $u->position),
            'positionId' => $u->hrm_position_id,
            'type' => $u->hrmEmploymentType?->name ?? 'Regular',
            'salary' => $u->basic_salary !== null ? (float) $u->basic_salary : null,
            'lastAdjustment' => isset($lastApplied[$u->id])
                ? \Carbon\Carbon::parse($lastApplied[$u->id])->format('M j, Y')
                : '—',
            'hasOpen' => isset($openByUser[$u->id]),
        ])->values()->all();

        $adjustments = SalaryAdjustment::with([
                'employee:id,name,employee_id',
                'requester:id,name',
                'approver:id,name',
            ])
            ->latest()
            ->take(200)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'userId' => $a->user_id,
                'name' => $a->employee?->name ?? '—',
                'employeeId' => $a->employee?->employee_id ?: ('EMP-' . str_pad((string) $a->user_id, 4, '0', STR_PAD_LEFT)),
                'oldSalary' => $a->old_salary !== null ? (float) $a->old_salary : null,
                'newSalary' => (float) $a->new_salary,
                'type' => $a->adjustment_type,
                'reason' => $a->reason,
                'notes' => $a->notes,
                'effectiveDate' => $a->effective_date?->format('Y-m-d'),
                'status' => $a->status,
                'requestedBy' => $a->requester?->name ?? '—',
                'approvedBy' => $a->approver?->name,
                'decidedAt' => $a->decided_at?->format('M j, Y g:i A'),
            ])
            ->values()
            ->all();

        $histories = SalaryHistory::orderBy('effective_date')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->map(fn ($h) => [
                'date' => $h->effective_date?->format('M j, Y'),
                'prev' => $h->previous_salary !== null ? (float) $h->previous_salary : null,
                'next' => $h->new_salary !== null ? (float) $h->new_salary : null,
                'reason' => $h->reason ?? $h->source,
            ])->values()->all())
            ->all();

        $components = CompensationComponent::orderBy('name')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'amount' => (float) $c->amount,
                'frequency' => $c->frequency,
                'isActive' => (bool) $c->is_active,
            ])->values()->all())
            ->all();

        return Inertia::render('Dashboard/HRM_NEW/Compensation', [
            'employees' => $employees,
            'adjustments' => $adjustments,
            'histories' => $histories,
            'components' => $components,
            'approvedThisMonth' => SalaryAdjustment::whereIn('status', [
                    SalaryAdjustment::STATUS_APPROVED,
                    SalaryAdjustment::STATUS_SCHEDULED,
                    SalaryAdjustment::STATUS_APPLIED,
                ])
                ->whereMonth('decided_at', now()->month)
                ->whereYear('decided_at', now()->year)
                ->count(),
            'positions' => HrmPosition::whereNull('archived_at')
                ->select('id', 'name', 'department_id', 'salary_min', 'salary_max')
                ->orderBy('name')->get(),
            'departments' => HrmDepartment::whereNull('archived_at')
                ->select('id', 'name')->orderBy('name')->get(),
            'roles' => ['HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'PRO', 'PROJ', 'IT', 'LOG'],
            'adjustmentTypes' => SalaryAdjustment::TYPES,
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    /**
     * Draft a salary adjustment. The active salary is NOT touched here —
     * it only changes when an approved adjustment is applied. Pass
     * `submit: true` to move straight to Pending Approval.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'new_salary' => 'required|numeric|min:0',
            'adjustment_type' => 'nullable|string|max:60',
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'effective_date' => 'required|date',
            'document_path' => 'nullable|string|max:255',
            'submit' => 'nullable|boolean',
        ]);

        $employee = User::findOrFail($data['user_id']);

        // One open proposal per employee — prevents overlapping periods.
        if (SalaryAdjustment::where('user_id', $employee->id)
            ->whereIn('status', SalaryAdjustment::OPEN_STATUSES)->exists()) {
            return back()->withErrors(['user_id' => 'This employee already has an open salary proposal.']);
        }

        $adjustment = SalaryAdjustment::create([
            'user_id' => $employee->id,
            'old_salary' => $employee->basic_salary,
            'new_salary' => $data['new_salary'],
            'adjustment_type' => $data['adjustment_type'] ?? 'Merit Increase',
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'effective_date' => $data['effective_date'],
            'status' => SalaryAdjustment::STATUS_DRAFT,
            'requested_by' => $request->user()->id,
            'document_path' => $data['document_path'] ?? null,
        ]);

        if ($request->boolean('submit')) {
            $adjustment->update(['status' => SalaryAdjustment::STATUS_PENDING]);
        }

        return back()->with('success', $request->boolean('submit')
            ? 'Adjustment submitted for approval.'
            : 'Adjustment draft saved.');
    }

    /**
     * Edit a Draft proposal (terms only — never the active salary).
     */
    public function update(Request $request, SalaryAdjustment $adjustment)
    {
        if ($adjustment->status !== SalaryAdjustment::STATUS_DRAFT) {
            return back()->withErrors(['status' => 'Only Draft proposals can be edited.']);
        }

        $adjustment->update($request->validate([
            'new_salary' => 'sometimes|required|numeric|min:0',
            'adjustment_type' => 'nullable|string|max:60',
            'reason' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'effective_date' => 'sometimes|required|date',
            'document_path' => 'nullable|string|max:255',
        ]));

        return back()->with('success', 'Draft updated.');
    }

    public function submit(Request $request, SalaryAdjustment $adjustment)
    {
        if ($adjustment->status !== SalaryAdjustment::STATUS_DRAFT) {
            return back()->withErrors(['status' => 'Only Draft proposals can be submitted.']);
        }
        $adjustment->update(['status' => SalaryAdjustment::STATUS_PENDING]);

        return back()->with('success', 'Adjustment submitted for approval.');
    }

    public function approve(Request $request, SalaryAdjustment $adjustment)
    {
        if ($adjustment->status !== SalaryAdjustment::STATUS_PENDING) {
            return back()->withErrors(['status' => 'Only Pending Approval requests can be approved.']);
        }

        $data = $request->validate(['remarks' => 'nullable|string|max:2000']);

        DB::transaction(function () use ($request, $adjustment, $data) {
            $status = $adjustment->effective_date->isFuture()
                ? SalaryAdjustment::STATUS_SCHEDULED
                : SalaryAdjustment::STATUS_APPROVED;
            $adjustment->update([
                'status' => $status,
                'approved_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
            SalaryAdjustmentApproval::create([
                'salary_adjustment_id' => $adjustment->id,
                'decided_by' => $request->user()->id,
                'decision' => 'Approved',
                'remarks' => $data['remarks'] ?? null,
            ]);
        });

        return back()->with('success', 'Adjustment approved. It takes effect on its effective date.');
    }

    public function reject(Request $request, SalaryAdjustment $adjustment)
    {
        if ($adjustment->status !== SalaryAdjustment::STATUS_PENDING) {
            return back()->withErrors(['status' => 'Only Pending Approval requests can be rejected.']);
        }

        $data = $request->validate(['remarks' => 'nullable|string|max:2000']);

        DB::transaction(function () use ($request, $adjustment, $data) {
            $adjustment->update([
                'status' => SalaryAdjustment::STATUS_REJECTED,
                'approved_by' => $request->user()->id,
                'decided_at' => now(),
            ]);
            SalaryAdjustmentApproval::create([
                'salary_adjustment_id' => $adjustment->id,
                'decided_by' => $request->user()->id,
                'decision' => 'Rejected',
                'remarks' => $data['remarks'] ?? null,
            ]);
        });

        return back()->with('success', 'Adjustment rejected.');
    }

    public function cancel(Request $request, SalaryAdjustment $adjustment)
    {
        if (! in_array($adjustment->status, SalaryAdjustment::OPEN_STATUSES, true)) {
            return back()->withErrors(['status' => 'Only open proposals can be cancelled.']);
        }
        $adjustment->update(['status' => SalaryAdjustment::STATUS_CANCELLED]);

        return back()->with('success', 'Proposal cancelled. History retained.');
    }

    /**
     * Manually apply all due adjustments (same routine the scheduler runs).
     */
    public function applyDue(Request $request)
    {
        $count = SalaryAdjustmentApplier::applyDue();

        return back()->with('success', "Applied {$count} due adjustment(s).");
    }

    public function componentStore(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:120',
            'amount' => 'required|numeric|min:0',
            'frequency' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:1000',
        ]);

        CompensationComponent::create($data + ['is_active' => true, 'frequency' => $data['frequency'] ?? 'monthly']);

        return back()->with('success', 'Allowance component added.');
    }

    public function componentToggle(Request $request, CompensationComponent $component)
    {
        $component->update(['is_active' => ! $component->is_active]);

        return back()->with('success', $component->is_active ? 'Component activated.' : 'Component deactivated.');
    }
}
