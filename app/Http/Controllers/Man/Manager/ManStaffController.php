<?php

namespace App\Http\Controllers\Man\Manager;

use App\Http\Controllers\Controller;
use App\Models\Core\PagePermission;
use App\Models\Core\User;
use App\Models\Man\ManStaffTransferRequest;
use App\Models\Man\ManTaskAssignment;
use App\Support\ManufacturingRoles;
use App\Support\ManufacturingPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ManStaffController extends Controller
{
    private function supervisor(): User
    {
        $user = Auth::user();
        abort_unless(
            $user?->is_manufacturing_supervisor
                && in_array($user->supervisor_department, ManufacturingRoles::PRODUCTION_DEPARTMENTS, true),
            403,
            'Only knitting, dyeing, and finishing supervisors can use the department staff page.'
        );

        return $user;
    }

    public function index()
    {
        $supervisor = $this->supervisor();
        $department = $supervisor->supervisor_department;
        $departmentRoles = ManufacturingRoles::rolesForDepartment($department);

        $staff = User::query()
            ->where('role', 'MAN')
            ->where('position', 'staff')
            ->whereIn('manufacturing_role', $departmentRoles)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'employee_id', 'manufacturing_role', 'profile_photo_path', 'is_active'])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'employee_id' => $member->employee_id,
                'manufacturing_role' => $member->manufacturing_role,
                'role_label' => ManufacturingRoles::label($member->manufacturing_role),
                'photo' => $member->profile_photo_path
                    ? '/storage/' . ltrim($member->profile_photo_path, '/')
                    : null,
                'is_active' => (bool) $member->is_active,
            ])->values();

        $pendingStaffIds = ManStaffTransferRequest::query()
            ->where('status', 'pending')
            ->pluck('staff_user_id');
        $otherDepartments = array_values(array_diff(ManufacturingRoles::PRODUCTION_DEPARTMENTS, [$department]));
        $otherRoles = collect($otherDepartments)
            ->flatMap(fn (string $otherDepartment) => ManufacturingRoles::rolesForDepartment($otherDepartment))
            ->all();
        $availableStaff = User::query()
            ->where('role', 'MAN')
            ->where('position', 'staff')
            ->where('is_active', true)
            ->whereIn('manufacturing_role', $otherRoles)
            ->whereNotIn('id', $pendingStaffIds)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id', 'manufacturing_role', 'profile_photo_path'])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'employee_id' => $member->employee_id,
                'manufacturing_role' => $member->manufacturing_role,
                'role_label' => ManufacturingRoles::label($member->manufacturing_role),
                'department' => ManufacturingRoles::departmentForRole($member->manufacturing_role),
                'photo' => $member->profile_photo_path
                    ? '/storage/' . ltrim($member->profile_photo_path, '/')
                    : null,
            ])->values();

        $requestColumns = ['id', 'requester_supervisor_id', 'staff_user_id', 'source_department', 'target_department', 'target_role', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note', 'created_at'];
        $incomingRequests = ManStaffTransferRequest::query()
            ->with(['requester:id,name', 'staffMember:id,name,employee_id,manufacturing_role', 'reviewer:id,name'])
            ->where('source_department', $department)
            ->latest()
            ->limit(100)
            ->get($requestColumns)
            ->map(fn (ManStaffTransferRequest $transfer) => $this->transferPayload($transfer));
        $outgoingRequests = ManStaffTransferRequest::query()
            ->with(['staffMember:id,name,employee_id,manufacturing_role', 'reviewer:id,name'])
            ->where('requester_supervisor_id', $supervisor->id)
            ->latest()
            ->limit(100)
            ->get($requestColumns)
            ->map(fn (ManStaffTransferRequest $transfer) => $this->transferPayload($transfer));

        $incomingStaffIds = $incomingRequests->pluck('staff.id')->filter()->unique()->values();
        $activeAssignments = ManTaskAssignment::query()
            ->whereIn('assigned_to', $incomingStaffIds)
            ->active()
            ->orderBy('id')
            ->get(['id', 'assigned_to', 'department', 'stage', 'manufacturing_order_id', 'sales_order_id'])
            ->groupBy('assigned_to');
        $incomingRequests = $incomingRequests->map(function (array $transfer) use ($activeAssignments) {
            $staffId = $transfer['staff']['id'] ?? null;
            $transfer['active_job_orders'] = $activeAssignments->get($staffId, collect())
                ->map(fn (ManTaskAssignment $assignment) => [
                    'id' => $assignment->id,
                    'department' => $assignment->department,
                    'stage' => $assignment->stage,
                    'manufacturing_order_id' => $assignment->manufacturing_order_id,
                    'sales_order_id' => $assignment->sales_order_id,
                ])->values();
            return $transfer;
        });

        return Inertia::render('Dashboard/MAN/Manager/Staff', [
            'department' => $department,
            'departmentLabel' => ManufacturingRoles::departmentLabel($department),
            'staff' => $staff,
            'availableStaff' => $availableStaff,
            'targetRoles' => collect($departmentRoles)->map(fn (string $role) => [
                'value' => $role,
                'label' => ManufacturingRoles::label($role),
            ])->values(),
            'incomingRequests' => $incomingRequests,
            'outgoingRequests' => $outgoingRequests,
        ]);
    }

    public function requestTransfer(Request $request)
    {
        $supervisor = $this->supervisor();
        $roles = ManufacturingRoles::rolesForDepartment($supervisor->supervisor_department);
        $validated = $request->validate([
            'staff_user_ids' => 'required|array|min:1',
            'staff_user_ids.*' => 'required|integer|distinct|exists:users,id',
            'target_role' => 'required|string|in:' . implode(',', $roles),
            'reason' => 'nullable|string|max:1500',
        ]);

        DB::transaction(function () use ($supervisor, $validated) {
            $staffMembers = User::query()
                ->whereIn('id', $validated['staff_user_ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($staffMembers->count() !== count($validated['staff_user_ids'])) {
                throw ValidationException::withMessages(['staff_user_ids' => 'One or more selected employees could not be found.']);
            }

            $pendingIds = ManStaffTransferRequest::query()
                ->whereIn('staff_user_id', $staffMembers->pluck('id'))
                ->where('status', 'pending')
                ->pluck('staff_user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if ($pendingIds) {
                throw ValidationException::withMessages(['staff_user_ids' => 'A selected employee already has a pending transfer request. Refresh the list and try again.']);
            }

            $sourceSupervisors = [];
            foreach ($staffMembers as $staff) {
                $sourceDepartment = ManufacturingRoles::departmentForRole($staff->manufacturing_role);
                if ($staff->role !== 'MAN' || $staff->position !== 'staff' || ! $staff->is_active
                    || ! $sourceDepartment
                    || ! in_array($sourceDepartment, ManufacturingRoles::PRODUCTION_DEPARTMENTS, true)
                    || $sourceDepartment === $supervisor->supervisor_department) {
                    throw ValidationException::withMessages([
                        'staff_user_ids' => 'Choose only active staff members from other production departments.',
                    ]);
                }

                if (! array_key_exists($sourceDepartment, $sourceSupervisors)) {
                    $sourceSupervisors[$sourceDepartment] = User::query()
                        ->where('is_manufacturing_supervisor', true)
                        ->where('supervisor_department', $sourceDepartment)
                        ->orderBy('id')
                        ->first();
                }
                if (! $sourceSupervisors[$sourceDepartment]) {
                    throw ValidationException::withMessages([
                        'staff_user_ids' => 'A selected employee’s department does not currently have a supervisor to review the request.',
                    ]);
                }
            }

            foreach ($staffMembers as $staff) {
                $sourceDepartment = ManufacturingRoles::departmentForRole($staff->manufacturing_role);
                ManStaffTransferRequest::create([
                    'requester_supervisor_id' => $supervisor->id,
                    'source_supervisor_id' => $sourceSupervisors[$sourceDepartment]->id,
                    'staff_user_id' => $staff->id,
                    'source_department' => $sourceDepartment,
                    'target_department' => $supervisor->supervisor_department,
                    'target_role' => $validated['target_role'],
                    'reason' => $validated['reason'] ?? null,
                ]);
            }
        });

        $count = count($validated['staff_user_ids']);
        return back()->with('success', $count === 1
            ? 'Transfer request sent to the staff member’s current department supervisor.'
            : "{$count} transfer requests sent to the selected staff members’ current department supervisors.");
    }

    public function decideTransfer(Request $request, ManStaffTransferRequest $transferRequest)
    {
        $supervisor = $this->supervisor();
        abort_unless($transferRequest->source_department === $supervisor->supervisor_department, 403, 'Only the staff member’s current department supervisor can decide this request.');

        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'review_note' => 'nullable|string|max:1500',
        ]);

        DB::transaction(function () use ($supervisor, $transferRequest, $validated) {
            $lockedRequest = ManStaffTransferRequest::query()->lockForUpdate()->findOrFail($transferRequest->id);
            if ($lockedRequest->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This transfer request has already been reviewed.']);
            }

            if ($validated['decision'] === 'approved') {
                $staff = User::query()->lockForUpdate()->findOrFail($lockedRequest->staff_user_id);
                if (ManTaskAssignment::query()->where('assigned_to', $staff->id)->active()->exists()) {
                    throw ValidationException::withMessages(['decision' => 'This staff member still has unfinished Job Orders. Complete or reassign them before approving the transfer.']);
                }
                $currentDepartment = ManufacturingRoles::departmentForRole($staff->manufacturing_role);
                if ($staff->role !== 'MAN' || $staff->position !== 'staff' || ! $staff->is_active
                    || $currentDepartment !== $lockedRequest->source_department
                    || ! in_array($lockedRequest->target_role, ManufacturingRoles::rolesForDepartment($lockedRequest->target_department), true)) {
                    throw ValidationException::withMessages(['decision' => 'The staff member or requested department has changed. The transfer was not applied.']);
                }

                $oldRole = $staff->manufacturing_role;
                $staff->update(['manufacturing_role' => $lockedRequest->target_role]);
                $this->prepareRolePagePermissions($staff, $oldRole, $lockedRequest->target_role);
            }

            $lockedRequest->update([
                'status' => $validated['decision'],
                'reviewed_by' => $supervisor->id,
                'reviewed_at' => now(),
                'review_note' => $validated['review_note'] ?? null,
            ]);
        });

        return back()->with('success', $validated['decision'] === 'approved'
            ? 'Transfer approved. The staff member is now assigned to your department.'
            : 'Transfer request declined. The staff member remains in your department.');
    }

    public function bulkDecideTransfers(Request $request)
    {
        $supervisor = $this->supervisor();
        $validated = $request->validate([
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'required|integer|distinct|exists:man_staff_transfer_requests,id',
            'decision' => 'required|in:approved,rejected',
        ]);

        DB::transaction(function () use ($supervisor, $validated) {
            $transfers = ManStaffTransferRequest::query()
                ->whereIn('id', $validated['request_ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($transfers->count() !== count($validated['request_ids'])
                || $transfers->contains(fn (ManStaffTransferRequest $transfer) => $transfer->status !== 'pending'
                    || $transfer->source_department !== $supervisor->supervisor_department)) {
                throw ValidationException::withMessages([
                    'request_ids' => 'Some selected requests are no longer pending or are outside your department.',
                ]);
            }

            foreach ($transfers as $transfer) {
                if ($validated['decision'] === 'approved') {
                    $staff = User::query()->lockForUpdate()->findOrFail($transfer->staff_user_id);
                    if (ManTaskAssignment::query()->where('assigned_to', $staff->id)->active()->exists()) {
                        throw ValidationException::withMessages([
                            'request_ids' => 'A selected staff member still has unfinished Job Orders. Complete or reassign them before approving these transfers.',
                        ]);
                    }
                    $currentDepartment = ManufacturingRoles::departmentForRole($staff->manufacturing_role);
                    if ($staff->role !== 'MAN' || $staff->position !== 'staff' || ! $staff->is_active
                        || $currentDepartment !== $transfer->source_department
                        || ! in_array($transfer->target_role, ManufacturingRoles::rolesForDepartment($transfer->target_department), true)) {
                        throw ValidationException::withMessages([
                            'request_ids' => 'A selected staff member or requested department has changed. No requests were applied.',
                        ]);
                    }

                    $oldRole = $staff->manufacturing_role;
                    $staff->update(['manufacturing_role' => $transfer->target_role]);
                    $this->prepareRolePagePermissions($staff, $oldRole, $transfer->target_role);
                }

                $transfer->update([
                    'status' => $validated['decision'],
                    'reviewed_by' => $supervisor->id,
                    'reviewed_at' => now(),
                ]);
            }
        });

        $count = count($validated['request_ids']);
        return back()->with('success', $validated['decision'] === 'approved'
            ? "{$count} staff transfer request(s) approved."
            : "{$count} staff transfer request(s) declined.");
    }

    public function cancelTransfer(ManStaffTransferRequest $transferRequest)
    {
        $supervisor = $this->supervisor();
        abort_unless((int) $transferRequest->requester_supervisor_id === (int) $supervisor->id, 403, 'Only the requesting supervisor can cancel this request.');
        abort_unless($transferRequest->status === 'pending', 422, 'Only pending transfer requests can be cancelled.');

        $transferRequest->update(['status' => 'cancelled']);

        return back()->with('success', 'Transfer request cancelled.');
    }

    private function prepareRolePagePermissions(User $staff, string $oldRole, string $newRole): void
    {
        // Remove the previous role’s private workspace grant. Shared dashboard,
        // history and report grants stay with the employee.
        PagePermission::query()
            ->where('user_id', $staff->id)
            ->whereIn('module', ['MAN', 'man'])
            ->whereRaw('LOWER(page) = ?', [strtolower($oldRole)])
            ->delete();

        // A transfer gives the employee the normal workspace permissions for
        // the role they are moving into. Existing IT choices for a target role
        // page are preserved; missing page rows follow the MAN staff default.
        foreach (array_keys(ManufacturingPages::forRole($newRole)) as $page) {
            $exists = PagePermission::query()
                ->where('user_id', $staff->id)
                ->whereIn('module', ['MAN', 'man'])
                ->whereRaw('LOWER(page) = ?', [strtolower($page)])
                ->exists();

            if (! $exists) {
                PagePermission::create([
                    'user_id' => $staff->id,
                    'module' => 'MAN',
                    'page' => strtolower($page),
                    'permission_level' => 'edit',
                ]);
            }
        }
    }

    private function transferPayload(ManStaffTransferRequest $transfer): array
    {
        return [
            'id' => $transfer->id,
            'staff' => $transfer->staffMember ? [
                'id' => $transfer->staffMember->id,
                'name' => $transfer->staffMember->name,
                'employee_id' => $transfer->staffMember->employee_id,
                'current_role' => $transfer->staffMember->manufacturing_role,
            ] : null,
            'requester' => $transfer->requester?->name,
            'source_department' => $transfer->source_department,
            'target_department' => $transfer->target_department,
            'target_role' => $transfer->target_role,
            'reason' => $transfer->reason,
            'status' => $transfer->status,
            'reviewer' => $transfer->reviewer?->name,
            'reviewed_at' => $transfer->reviewed_at,
            'review_note' => $transfer->review_note,
            'created_at' => $transfer->created_at,
        ];
    }
}
