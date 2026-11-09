<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\AdvanceApproval;
use App\Models\Hrm\AdvanceAuditLog;
use App\Models\Hrm\AdvancePayment;
use App\Models\Hrm\Payroll;
use App\Services\Payroll\AdvanceRepaymentService;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdvancePaymentController extends Controller
{
    use HasPagePermissions;

    public function index()
    {
        $permissions = $this->getPagePermissionsForModule('HRM');
        $isHr = $this->isHr();
        $isManager = $this->isManager();
        $isFinance = $this->isFinance();
        $advances = AdvancePayment::with([
            'approvals.approver:id,name',
            'repayments.payroll:id,period',
            'deductions.payroll:id,period',
            'auditLogs.actor:id,name',
        ])->latest()->get();

        $records = $advances->map(fn (AdvancePayment $advance) => $this->serializeAdvance(
            $advance,
            $isHr,
            $isManager,
            $isFinance
        ));
        $employees = User::with(['hrmDepartment:id,name', 'hrmOrgPosition:id,name'])
            ->where('is_active', true)
            ->whereNotNull('employee_id')
            ->where('employee_id', '<>', '')
            ->whereIn('position', ['staff', 'manager', 'trainee'])
            ->orderBy('name')
            ->get(['id', 'employee_id', 'name', 'department', 'position', 'hrm_department_id', 'hrm_position_id'])
            ->map(fn (User $employee) => [
                'id' => $employee->employee_id,
                'name' => $employee->name,
                'department' => $employee->hrmDepartment?->name ?: $employee->department ?: 'Unassigned',
                'position' => $employee->hrmOrgPosition?->name ?: ucfirst((string) $employee->position),
            ]);

        $active = $advances->whereNotIn('status', ['Rejected', 'Cancelled']);
        $released = $advances->whereIn('status', ['Released', 'Partially Repaid', 'Fully Repaid']);

        return Inertia::render('Dashboard/HRM_NEW/PayrollAdvances', [
            'advances' => $records,
            'employees' => $employees,
            'summary' => [
                'total_advances' => $active->count(),
                'pending_approval' => $advances->where('status', 'Pending Approval')->count(),
                'released_amount' => (float) $released->sum('advance_amount'),
                'outstanding_balance' => (float) $released->sum('outstanding_balance'),
                'allowance_total' => (float) Payroll::where('period', \App\Support\PayPeriods::currentLabel())->sum('allowance'),
                'allowance_period' => \App\Support\PayPeriods::currentLabel(),
            ],
            'permissions' => [
                'can_create' => $isHr && ($permissions['payroll'] ?? 'view') === 'edit',
                'is_hr' => $isHr,
                'is_manager' => $isManager,
                'is_finance' => $isFinance,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeHrEdit();
        $data = $this->validatedForm($request);
        $employee = User::with(['hrmDepartment:id,name', 'hrmOrgPosition:id,name'])
            ->where('employee_id', $data['employee_id'])->firstOrFail();

        $document = $request->file('authorization_document');
        $path = $document?->store('hrm/advance-payment-documents', 'local');
        $advance = DB::transaction(function () use ($data, $employee, $document, $path) {
            $advance = AdvancePayment::create([
                'user_id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'employee_name' => $employee->name,
                'department' => $employee->hrmDepartment?->name ?: $employee->department,
                'position' => $employee->hrmOrgPosition?->name ?: ucfirst((string) $employee->position),
                'advance_amount' => $data['advance_amount'],
                'reason' => $data['reason'],
                'request_date' => $data['request_date'],
                'planned_release_date' => $data['planned_release_date'],
                'payment_method' => $data['payment_method'],
                'repayment_method' => $data['repayment_method'],
                'installments' => $data['repayment_method'] === 'installment' ? $data['installments'] : null,
                'installment_amount' => $data['repayment_method'] === 'installment'
                    ? round((float) $data['advance_amount'] / max(1, (int) $data['installments']), 2)
                    : null,
                'remarks' => $data['remarks'] ?? null,
                'payroll_deduction_authorized' => (bool) ($data['payroll_deduction_authorized'] ?? false),
                'authorization_document_path' => $path,
                'authorization_document_name' => $document?->getClientOriginalName(),
                'status' => 'Draft',
                'total_repaid' => 0,
                'outstanding_balance' => 0,
                'created_by' => auth()->id(),
            ]);
            $advance->update(['advance_number' => 'ADV-'.now()->format('Y').'-'.str_pad((string) $advance->id, 6, '0', STR_PAD_LEFT)]);
            $this->audit($advance, 'created', null, 'Draft', null, [
                'advance_number' => $advance->advance_number,
                'employee_id' => $advance->employee_id,
                'advance_amount' => (float) $advance->advance_amount,
                'reason' => $advance->reason,
                'request_date' => $advance->request_date?->toDateString(),
                'planned_release_date' => $advance->planned_release_date?->toDateString(),
                'payment_method' => $advance->payment_method,
                'repayment_method' => $advance->repayment_method,
                'installments' => $advance->installments,
                'payroll_deduction_authorized' => (bool) $advance->payroll_deduction_authorized,
                'authorization_document_attached' => (bool) $advance->authorization_document_path,
            ]);

            return $advance;
        });

        return redirect()->route('hrm.payroll.advances.index')->with('message', $advance->advance_number.' saved as Draft.');
    }

    public function update(Request $request, AdvancePayment $advance)
    {
        $this->authorizeHrEdit();
        abort_unless($advance->status === 'Draft', 422, 'Only Draft advances can be edited.');
        $data = $this->validatedForm($request);
        $employee = User::with(['hrmDepartment:id,name', 'hrmOrgPosition:id,name'])
            ->where('employee_id', $data['employee_id'])->firstOrFail();
        $document = $request->file('authorization_document');
        $newPath = $document?->store('hrm/advance-payment-documents', 'local');
        $previousStatus = $advance->status;
        $previousPath = $advance->authorization_document_path;
        $before = $advance->only([
            'employee_id', 'employee_name', 'department', 'position', 'advance_amount', 'reason',
            'request_date', 'planned_release_date', 'payment_method', 'repayment_method',
            'installments', 'installment_amount', 'remarks', 'payroll_deduction_authorized',
        ]);

        $advance->update([
            'user_id' => $employee->id,
            'employee_id' => $employee->employee_id,
            'employee_name' => $employee->name,
            'department' => $employee->hrmDepartment?->name ?: $employee->department,
            'position' => $employee->hrmOrgPosition?->name ?: ucfirst((string) $employee->position),
            'advance_amount' => $data['advance_amount'],
            'reason' => $data['reason'],
            'request_date' => $data['request_date'],
            'planned_release_date' => $data['planned_release_date'],
            'payment_method' => $data['payment_method'],
            'repayment_method' => $data['repayment_method'],
            'installments' => $data['repayment_method'] === 'installment' ? $data['installments'] : null,
            'installment_amount' => $data['repayment_method'] === 'installment'
                ? round((float) $data['advance_amount'] / max(1, (int) $data['installments']), 2)
                : null,
            'remarks' => $data['remarks'] ?? null,
            'payroll_deduction_authorized' => (bool) ($data['payroll_deduction_authorized'] ?? false),
            'authorization_document_path' => $newPath ?: $previousPath,
            'authorization_document_name' => $document?->getClientOriginalName() ?: $advance->authorization_document_name,
        ]);
        if ($newPath && $previousPath) {
            Storage::disk('local')->delete($previousPath);
        }
        $this->audit($advance, 'updated', $previousStatus, $advance->status, null, [
            'before' => $before,
            'after' => $advance->only(array_keys($before)),
            'authorization_document_replaced' => (bool) $document,
        ]);

        return back()->with('message', $advance->advance_number.' updated.');
    }

    public function submit(AdvancePayment $advance)
    {
        $this->authorizeHrEdit();
        abort_unless($advance->status === 'Draft', 422, 'Only Draft advances can be submitted.');
        if (! $advance->payroll_deduction_authorized || ! $advance->authorization_document_path) {
            return back()->withErrors([
                'authorization_document' => 'Attach the employee’s signed payroll deduction authorization and confirm it before submitting.',
            ]);
        }

        DB::transaction(function () use ($advance) {
            $record = AdvancePayment::whereKey($advance->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'Draft', 422, 'Only Draft advances can be submitted.');
            abort_unless($record->payroll_deduction_authorized && $record->authorization_document_path, 422, 'Employee authorization is required before submission.');
            $record->update(['status' => 'Pending Approval']);
            $this->audit($record, 'submitted', 'Draft', 'Pending Approval');
        });

        return back()->with('message', $advance->advance_number.' submitted for manager approval.');
    }

    public function decide(Request $request, AdvancePayment $advance)
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(['manager', 'hr', 'finance'])],
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'remarks' => ['nullable', 'string', 'max:3000', Rule::requiredIf($request->input('decision') === 'rejected')],
        ]);
        $this->authorizeStage($data['stage']);

        DB::transaction(function () use ($advance, $data) {
            $record = AdvancePayment::whereKey($advance->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'Pending Approval', 422, 'This advance is no longer awaiting approval.');
            $expectedStage = $this->expectedStage($record);
            abort_unless($expectedStage === $data['stage'], 422, 'Approval must follow Manager, HR, then Finance review.');
            abort_unless((int) $record->created_by !== (int) auth()->id() && (int) $record->user_id !== (int) auth()->id(), 403, 'The requester and employee cannot approve their own advance.');
            abort_unless(! $record->approvals()->where('approver_id', auth()->id())->exists(), 422, 'Each approval stage must be reviewed by a different authorized user.');

            AdvanceApproval::create([
                'advance_id' => $record->id,
                'stage' => $data['stage'],
                'decision' => $data['decision'],
                'approver_id' => auth()->id(),
                'decided_at' => now(),
                'remarks' => $data['remarks'] ?? null,
            ]);

            $previous = $record->status;
            if ($data['decision'] === 'rejected') {
                $record->update(['status' => 'Rejected']);
            } elseif ($data['stage'] === 'finance') {
                $record->update(['status' => 'Approved for Release']);
            }

            $this->audit(
                $record,
                $data['stage'].'_'.$data['decision'],
                $previous,
                $record->status,
                $data['remarks'] ?? null,
                ['stage' => $data['stage'], 'decision' => $data['decision']]
            );
        });

        return back()->with('message', $data['decision'] === 'approved' ? 'Advance approval recorded.' : 'Advance request rejected.');
    }

    public function release(Request $request, AdvancePayment $advance)
    {
        abort_unless($this->isFinance(), 403, 'Only Finance can release an advance payment.');
        $data = $request->validate([
            'payment_reference' => 'required|string|max:100',
            'actual_release_date' => 'required|date|before_or_equal:today',
            'remarks' => 'nullable|string|max:3000',
        ]);

        DB::transaction(function () use ($advance, $data) {
            $record = AdvancePayment::whereKey($advance->id)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'Approved for Release', 422, 'This advance is not approved for release.');
            abort_unless(
                $record->approvals()->whereIn('stage', ['manager', 'hr', 'finance'])->where('decision', 'approved')->count() === 3,
                422,
                'Manager, HR, and Finance approvals must all be complete before release.'
            );

            $record->update([
                'status' => 'Released',
                'total_repaid' => 0,
                'outstanding_balance' => $record->advance_amount,
                'payment_reference' => $data['payment_reference'],
                'actual_release_date' => $data['actual_release_date'],
                'released_by' => auth()->id(),
            ]);

            $employee = User::find($record->user_id);
            if ($employee) {
                $upcomingDrafts = Payroll::where('employee_id', $record->employee_id)
                    ->where('status', 'Draft')
                    ->whereDate('cutoff_start', '>=', $data['actual_release_date'])
                    ->orderBy('cutoff_start')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                foreach ($upcomingDrafts as $payroll) {
                    $deduction = AdvanceRepaymentService::reserveForPayroll($payroll, $employee, (float) $payroll->net_pay);
                    if ($deduction > 0) {
                        $payroll->update([
                            'advance_deduction' => round((float) $payroll->advance_deduction + $deduction, 2),
                            'net_pay' => max(0, round((float) $payroll->net_pay - $deduction, 2)),
                        ]);
                    }
                }
            }
            $this->audit($record, 'released', 'Approved for Release', 'Released', $data['remarks'] ?? null, [
                'payment_reference' => $data['payment_reference'],
                'actual_release_date' => $data['actual_release_date'],
            ]);
        });

        return back()->with('message', $advance->advance_number.' released by Finance.');
    }

    public function cancel(Request $request, AdvancePayment $advance)
    {
        abort_unless($this->isHr() || (int) $advance->created_by === (int) auth()->id(), 403);
        $data = $request->validate(['remarks' => 'nullable|string|max:3000']);
        DB::transaction(function () use ($advance, $data) {
            $record = AdvancePayment::whereKey($advance->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($record->status, ['Draft', 'Pending Approval', 'Approved for Release'], true), 422, 'Released advances cannot be cancelled; record repayments instead.');
            $previous = $record->status;
            $record->update([
                'status' => 'Cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ]);
            $this->audit($record, 'cancelled', $previous, 'Cancelled', $data['remarks'] ?? null);
        });

        return back()->with('message', $advance->advance_number.' cancelled.');
    }

    public function document(AdvancePayment $advance)
    {
        abort_unless($advance->authorization_document_path && Storage::disk('local')->exists($advance->authorization_document_path), 404);

        return Storage::disk('local')->download(
            $advance->authorization_document_path,
            $advance->authorization_document_name ?: 'advance-authorization'
        );
    }

    private function validatedForm(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'string', Rule::exists('users', 'employee_id')->where(fn ($query) => $query->where('is_active', true)->whereIn('position', ['staff', 'manager', 'trainee']))],
            'advance_amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'reason' => 'required|string|max:2000',
            'request_date' => 'required|date',
            'planned_release_date' => 'required|date|after_or_equal:request_date',
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'e_wallet'])],
            'repayment_method' => ['required', Rule::in(['one_time', 'installment'])],
            'installments' => ['required_if:repayment_method,installment', 'nullable', 'integer', 'min:2', 'max:36'],
            'remarks' => 'nullable|string|max:3000',
            'payroll_deduction_authorized' => 'nullable|boolean',
            'authorization_document' => 'nullable|file|max:10240|mimes:pdf,png,jpg,jpeg,doc,docx',
        ]);
    }

    private function serializeAdvance(AdvancePayment $advance, bool $isHr, bool $isManager, bool $isFinance): array
    {
        $approvals = $advance->approvals;
        $expectedStage = $advance->status === 'Pending Approval' ? $this->expectedStage($advance) : null;
        $isCreator = (int) $advance->created_by === (int) auth()->id();

        return [
            'id' => $advance->id,
            'advance_number' => $advance->advance_number,
            'employee_id' => $advance->employee_id,
            'employee_name' => $advance->employee_name,
            'department' => $advance->department ?: 'Unassigned',
            'position' => $advance->position ?: '—',
            'advance_amount' => (float) $advance->advance_amount,
            'reason' => $advance->reason,
            'request_date' => $advance->request_date?->toDateString(),
            'planned_release_date' => $advance->planned_release_date?->toDateString(),
            'payment_method' => $advance->payment_method,
            'repayment_method' => $advance->repayment_method,
            'installments' => $advance->installments,
            'installment_amount' => (float) ($advance->installment_amount ?? 0),
            'remarks' => $advance->remarks,
            'payroll_deduction_authorized' => (bool) $advance->payroll_deduction_authorized,
            'authorization_document_name' => $advance->authorization_document_name,
            'authorization_url' => $advance->authorization_document_path ? route('hrm.payroll.advances.document', $advance) : null,
            'status' => $advance->status,
            'total_repaid' => (float) $advance->total_repaid,
            'outstanding_balance' => (float) $advance->outstanding_balance,
            'payment_reference' => $advance->payment_reference,
            'actual_release_date' => $advance->actual_release_date?->toDateString(),
            'approvals' => $approvals->map(fn (AdvanceApproval $approval) => [
                'stage' => $approval->stage,
                'decision' => $approval->decision,
                'approver' => $approval->approver?->name ?? 'Former user',
                'decided_at' => $approval->decided_at?->toDateTimeString(),
                'remarks' => $approval->remarks,
            ])->values(),
            'repayments' => $advance->repayments->map(fn ($repayment) => [
                'payroll_id' => $repayment->payroll_id,
                'payroll_period' => $repayment->payroll_period,
                'amount' => (float) $repayment->amount,
                'repaid_at' => $repayment->repaid_at?->toDateTimeString(),
            ])->values(),
            'deductions' => $advance->deductions->map(fn ($deduction) => [
                'payroll_id' => $deduction->payroll_id,
                'payroll_period' => $deduction->payroll?->period,
                'amount' => (float) $deduction->deduction_amount,
                'status' => $deduction->status,
            ])->values(),
            'audit_logs' => $advance->auditLogs->map(fn ($log) => [
                'action' => $log->action,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'actor' => $log->actor?->name ?? 'System',
                'remarks' => $log->remarks,
                'details' => $log->details,
                'created_at' => $log->created_at?->toDateTimeString(),
            ])->values(),
            'can_edit' => $isHr && $advance->status === 'Draft',
            'can_submit' => $isHr && $advance->status === 'Draft',
            'can_decide' => $advance->status === 'Pending Approval' && $this->userCanDecide($expectedStage, $isManager, $isHr, $isFinance),
            'pending_stage' => $expectedStage,
            'can_release' => $isFinance && $advance->status === 'Approved for Release',
            'can_cancel' => ($isHr || $isCreator) && in_array($advance->status, ['Draft', 'Pending Approval', 'Approved for Release'], true),
        ];
    }

    private function expectedStage(AdvancePayment $advance): ?string
    {
        $decisions = $advance->relationLoaded('approvals')
            ? $advance->approvals->keyBy('stage')
            : $advance->approvals()->get()->keyBy('stage');
        foreach (['manager', 'hr', 'finance'] as $stage) {
            if (! $decisions->has($stage)) {
                return $stage;
            }
            if ($decisions->get($stage)->decision !== 'approved') {
                return null;
            }
        }

        return null;
    }

    private function userCanDecide(?string $stage, bool $isManager, bool $isHr, bool $isFinance): bool
    {
        return match ($stage) {
            'manager' => $isManager,
            'hr' => $isHr,
            'finance' => $isFinance,
            default => false,
        };
    }

    private function authorizeStage(string $stage): void
    {
        $allowed = match ($stage) {
            'manager' => $this->isManager(),
            'hr' => $this->isHr(),
            'finance' => $this->isFinance(),
            default => false,
        };
        abort_unless($allowed, 403, 'You are not authorized for this approval stage.');
    }

    private function authorizeHrEdit(): void
    {
        $permissions = $this->getPagePermissionsForModule('HRM');
        abort_unless($this->isHr() && ($permissions['payroll'] ?? 'view') === 'edit', 403);
    }

    private function isHr(): bool
    {
        return strtoupper((string) auth()->user()?->role) === 'HRM';
    }

    private function isManager(): bool
    {
        $user = auth()->user();
        return $user && (
            in_array(strtolower((string) $user->position), ['manager', 'secretary', 'special_officer'], true)
            || in_array(strtoupper((string) $user->role), ['CEO', 'COO'], true)
        );
    }

    private function isFinance(): bool
    {
        return strtoupper((string) auth()->user()?->role) === 'FIN';
    }

    private function audit(AdvancePayment $advance, string $action, ?string $from, ?string $to, ?string $remarks = null, array $details = []): void
    {
        AdvanceAuditLog::create([
            'advance_id' => $advance->id,
            'actor_id' => auth()->id(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
            'details' => $details ?: null,
            'created_at' => now(),
        ]);
    }
}
