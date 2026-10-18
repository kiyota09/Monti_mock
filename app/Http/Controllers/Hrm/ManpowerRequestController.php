<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Mail\EmployeeWelcomeMail;
use App\Models\Core\User;
use App\Models\Hrm\Agency;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmShift;
use App\Models\Hrm\ManpowerAssignment;
use App\Models\Hrm\ManpowerProposal;
use App\Models\Hrm\ManpowerRequest;
use App\Models\Hrm\Worker;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Manpower requests (HRM > Requests, Pages 8-10).
 *
 * Lifecycle: Draft → Open → Partially filled → Filled → Closed, with
 * Cancelled reachable from Open/Partially filled/Filled (reason required).
 * Partially filled/Filled are derived from assigned vs needed — never set
 * by hand. Assign/release flips the worker Available ⇄ Deployed.
 */
class ManpowerRequestController extends Controller
{
    use HasPagePermissions;

    // ------------------------------------------------------------------
    // Page 8: Requests list
    // ------------------------------------------------------------------
    public function index(Request $request)
    {
        $q = ManpowerRequest::with(['agency:id,code,name'])->withCount([
            'activeAssignments as filled_count',
        ]);

        if ($s = $request->get('search')) {
            $q->where('code', 'like', "%{$s}%");
        }
        if ($agencyId = $request->get('agency_id')) {
            $q->where('agency_id', $agencyId);
        }
        if ($from = $request->get('from')) {
            $q->whereDate('start_date', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $q->whereDate('start_date', '<=', $to);
        }

        // Status filter applies to the *effective* status (derived for Open),
        // so filtering happens in memory after load (volumes are HR-scale).
        $status = $request->get('status');

        $all = $q->orderByDesc('id')->get();
        if ($status && $status !== 'All') {
            $all = $all->filter(fn ($r) => $r->effective_status === $status)->values();
        }

        $total = $all->count();
        $perPage = 15;
        $pageNum = max(1, min((int) $request->get('page', 1), max(1, (int) ceil($total / $perPage))));
        $items = $all->slice(($pageNum - 1) * $perPage, $perPage)->values()
            ->map(fn ($r) => $this->row($r))->all();

        $base = ManpowerRequest::query();

        return Inertia::render('Dashboard/HRM_NEW/Requests/Index', [
            'requests' => $items,
            'pagination' => [
                'current_page' => $pageNum,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
            'stats' => [
                'total' => (clone $base)->count(),
                'draft' => (clone $base)->where('status', ManpowerRequest::STATUS_DRAFT)->count(),
                'open' => (clone $base)->where('status', ManpowerRequest::STATUS_OPEN)->count(),
                'proposals_pending' => ManpowerProposal::where('status', ManpowerProposal::STATUS_PROPOSED)->count(),
            ],
            'agencies' => Agency::orderBy('name')->get(['id', 'code', 'name', 'status']),
            'filters' => $request->only(['search', 'agency_id', 'status', 'from', 'to']),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    // ------------------------------------------------------------------
    // Page 9: Create / Edit request
    // ------------------------------------------------------------------
    public function create()
    {
        return Inertia::render('Dashboard/HRM_NEW/Requests/Form', [
            'request' => null,
            'suggested_code' => self::nextCode(),
            'agencies' => Agency::where('status', Agency::STATUS_ACTIVE)->orderBy('name')->get(['id', 'code', 'name']),
            'departments' => \App\Models\Hrm\HrmDepartment::whereNull('archived_at')->orderBy('name')->get(['id', 'code', 'name']),
            'positions' => \App\Models\Hrm\HrmPosition::whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'department_id']),
            'shifts' => \App\Models\Hrm\HrmShift::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'start_time', 'end_time']),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function store(Request $request)
    {
        $submit = $request->boolean('submit');
        $data = $this->validated($request);

        $agency = Agency::findOrFail($data['agency_id']);
        if ($agency->status !== Agency::STATUS_ACTIVE) {
            return back()->withErrors(['agency_id' => 'Requests can only be placed with an Active agency.']);
        }

        if (empty($data['code'])) {
            $data['code'] = self::nextCode();
        }
        $data['status'] = $submit ? ManpowerRequest::STATUS_OPEN : ManpowerRequest::STATUS_DRAFT;
        unset($data['submit']);

        $manpower = ManpowerRequest::create($data);
        $this->log($manpower, $submit ? 'request_submitted' : 'request_created', "Request {$manpower->code} " . ($submit ? 'submitted (Open).' : 'saved as draft.'), null);

        return redirect()->route('hrm.manpower-requests.show', $manpower)
            ->with('success', $submit ? "Request {$manpower->code} submitted." : "Request {$manpower->code} saved as draft.");
    }

    public function edit(ManpowerRequest $manpowerRequest)
    {
        if (in_array($manpowerRequest->status, [ManpowerRequest::STATUS_CLOSED, ManpowerRequest::STATUS_CANCELLED], true)) {
            return redirect()->route('hrm.manpower-requests.show', $manpowerRequest)
                ->with('error', 'Closed or cancelled requests can no longer be edited.');
        }

        return Inertia::render('Dashboard/HRM_NEW/Requests/Form', [
            'request' => $this->detail($manpowerRequest->loadMissing(['agency:id,code,name', 'activeAssignments.worker:id,code,name'])),
            'suggested_code' => $manpowerRequest->code,
            'agencies' => Agency::where('status', Agency::STATUS_ACTIVE)->orderBy('name')->get(['id', 'code', 'name']),
            'departments' => \App\Models\Hrm\HrmDepartment::whereNull('archived_at')->orderBy('name')->get(['id', 'code', 'name']),
            'positions' => \App\Models\Hrm\HrmPosition::whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'department_id']),
            'shifts' => \App\Models\Hrm\HrmShift::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'start_time', 'end_time']),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function update(Request $request, ManpowerRequest $manpowerRequest)
    {
        if (in_array($manpowerRequest->status, [ManpowerRequest::STATUS_CLOSED, ManpowerRequest::STATUS_CANCELLED], true)) {
            return back()->with('error', 'Closed or cancelled requests can no longer be edited.');
        }

        $submit = $request->boolean('submit');
        $data = $this->validated($request, $manpowerRequest->id);
        unset($data['submit']);

        if (empty($data['code'])) {
            $data['code'] = $manpowerRequest->code;
        }

        // The agency cannot change while workers are actively assigned.
        if ((int) ($data['agency_id'] ?? $manpowerRequest->agency_id) !== (int) $manpowerRequest->agency_id) {
            if ($manpowerRequest->activeAssignments()->exists()) {
                return back()->withErrors(['agency_id' => 'Release all assigned workers before changing the agency.']);
            }
            $target = Agency::findOrFail($data['agency_id']);
            if ($target->status !== Agency::STATUS_ACTIVE) {
                return back()->withErrors(['agency_id' => 'Requests can only be placed with an Active agency.']);
            }
        }

        if ($submit && $manpowerRequest->status === ManpowerRequest::STATUS_DRAFT) {
            $data['status'] = ManpowerRequest::STATUS_OPEN;
        }

        $manpowerRequest->update($data);
        $this->log($manpowerRequest->fresh(), 'request_updated', "Request {$manpowerRequest->code} updated.", null);

        return redirect()->route('hrm.manpower-requests.show', $manpowerRequest)
            ->with('success', "Request {$manpowerRequest->code} updated.");
    }

    public function submit(ManpowerRequest $manpowerRequest)
    {
        if ($manpowerRequest->status !== ManpowerRequest::STATUS_DRAFT) {
            return back()->with('error', 'Only drafts can be submitted.');
        }
        if ($manpowerRequest->agency->status !== Agency::STATUS_ACTIVE) {
            return back()->with('error', 'Requests can only be submitted to an Active agency.');
        }

        $manpowerRequest->update(['status' => ManpowerRequest::STATUS_OPEN]);
        $this->log($manpowerRequest, 'request_submitted', "Request {$manpowerRequest->code} submitted (Open).", null);

        return back()->with('success', "Request {$manpowerRequest->code} is now Open.");
    }

    // ------------------------------------------------------------------
    // Page 10: Request detail
    // ------------------------------------------------------------------
    public function show(ManpowerRequest $manpowerRequest)
    {
        $manpowerRequest->loadMissing(['agency:id,code,name,status', 'activeAssignments.worker:id,code,name,position_skill,agency_id,status,daily_rate,phone,gender,clearance_expiry,resume_path,resume_original_name']);

        return Inertia::render('Dashboard/HRM_NEW/Requests/Detail', [
            'request' => $this->detail($manpowerRequest),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function cancel(Request $request, ManpowerRequest $manpowerRequest)
    {
        $request->validate(['reason' => 'required|string|max:2000']);

        if ($manpowerRequest->status !== ManpowerRequest::STATUS_OPEN) {
            return back()->with('error', 'Only Open requests (including partially filled / filled) can be cancelled.');
        }

        $manpowerRequest->update(['status' => ManpowerRequest::STATUS_CANCELLED, 'cancel_reason' => $request->reason]);
        $this->log($manpowerRequest, 'request_cancelled', "Request {$manpowerRequest->code} cancelled.", $request->reason);

        return back()->with('success', "Request {$manpowerRequest->code} cancelled.");
    }

    public function close(ManpowerRequest $manpowerRequest)
    {
        if ($manpowerRequest->effective_status !== ManpowerRequest::STATUS_FILLED) {
            return back()->with('error', 'Only Filled requests can be closed.');
        }

        $manpowerRequest->update(['status' => ManpowerRequest::STATUS_CLOSED]);
        $this->log($manpowerRequest, 'request_closed', "Request {$manpowerRequest->code} closed.", null);

        return back()->with('success', "Request {$manpowerRequest->code} closed.");
    }

    public function assign(Request $request, ManpowerRequest $manpowerRequest)
    {
        $data = $request->validate([
            'worker_id' => 'required|exists:workers,id',
            'company_email' => 'nullable|email|max:255',
            'start_date' => 'nullable|date',
        ]);
        $worker = Worker::findOrFail($data['worker_id']);

        if ($refusal = $this->assignmentRefusal($manpowerRequest, $worker)) {
            return back()->withErrors(['worker_id' => $refusal]);
        }
        if (ManpowerAssignment::where('request_id', $manpowerRequest->id)->where('worker_id', $worker->id)->whereNull('released_at')->exists()) {
            return back()->withErrors(['worker_id' => 'This worker is already assigned to this request.']);
        }

        // Same worker-account flow as proposal acceptance (confirm -> preview -> finish).
        [$companyEmail, $tempPassword, $trainingLabel, $mailSent, $recipient, $mailError, $employeeId] =
            $this->finalizeWorkerAccount($manpowerRequest, $worker, $data['company_email'] ?? null, $data['start_date'] ?? null);

        $this->createAssignment($manpowerRequest, $worker->fresh());

        return back()->with('success', $this->assignmentMessage($manpowerRequest, $worker, $mailSent, $recipient, $mailError))
            ->with('worker_credentials', [
                'worker' => $worker->name,
                'worker_code' => $worker->code,
                'employee_id' => $employeeId,
                'company_email' => $companyEmail,
                'password' => $tempPassword,
                'training_start' => $trainingLabel,
                'mailed' => $mailSent,
                'mailed_to' => $mailSent ? $recipient : null,
                'mail_error' => $mailError,
            ]);
    }

    /**
     * Shared assignment guards (HR direct assign + proposal acceptance).
     * Returns the refusal message, or null when assignment may proceed.
     */
    protected function assignmentRefusal(ManpowerRequest $manpowerRequest, Worker $worker): ?string
    {
        if ($manpowerRequest->status !== ManpowerRequest::STATUS_OPEN) {
            return 'Workers can only be assigned to Open requests.';
        }
        if ($manpowerRequest->agency->status !== Agency::STATUS_ACTIVE) {
            return 'No new workers can be assigned while the agency is not Active.';
        }
        if ($manpowerRequest->filled_count >= max(1, (int) $manpowerRequest->headcount)) {
            return 'This request is already filled.';
        }
        if ((int) $worker->agency_id !== (int) $manpowerRequest->agency_id) {
            return 'Only this agency’s own workers can be assigned.';
        }
        if ($worker->status !== Worker::STATUS_AVAILABLE) {
            return 'Only Available workers can be assigned.';
        }
        if ($worker->activeAssignment()->exists()) {
            return 'This worker is already on another active request.';
        }
        if ($worker->clearance_expired) {
            return 'This worker’s clearance has expired.';
        }

        return null;
    }

    protected function createAssignment(ManpowerRequest $manpowerRequest, Worker $worker): ManpowerAssignment
    {
        $assignment = ManpowerAssignment::create([
            'request_id' => $manpowerRequest->id,
            'worker_id' => $worker->id,
            'assigned_by' => auth()->id(),
            'assigned_by_name' => auth()->user()?->name,
            'assigned_at' => now(),
        ]);
        $worker->update(['status' => Worker::STATUS_DEPLOYED]);

        // Persist the shift picked at account creation (the request carries
        // it) onto the worker so the Shift Management page reflects it and
        // stays editable there (hrm_shift_worker pivot).
        if (! empty($manpowerRequest->shift)) {
            $shift = HrmShift::where('name', $manpowerRequest->shift)->first();
            if ($shift) {
                $worker->shifts()->syncWithoutDetaching([
                    $shift->id => [
                        'effective_date' => $manpowerRequest->start_date?->toDateString()
                            ?? $worker->availability,
                    ],
                ]);
            }
        }

        AgencyActivity::create([
            'agency_id' => $manpowerRequest->agency_id,
            'worker_id' => $worker->id,
            'request_id' => $manpowerRequest->id,
            'action' => 'worker_assigned',
            'details' => "Worker {$worker->code} assigned to request {$manpowerRequest->code}.",
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
        ]);

        return $assignment;
    }

    public function release(Request $request, ManpowerRequest $manpowerRequest, ManpowerAssignment $assignment)    {
        abort_unless((int) $assignment->request_id === (int) $manpowerRequest->id, 404);
        if ($assignment->released_at !== null) {
            return back()->with('error', 'This assignment was already released.');
        }

        $data = $request->validate(['reason' => 'required|string|max:2000']);

        $assignment->update(['released_at' => now(), 'released_reason' => $data['reason']]);

        $worker = $assignment->worker;
        if ($worker) {
            // Released workers become Available again — unless their agency
            // is no longer Active, in which case they go Inactive.
            $worker->update([
                'status' => $worker->agency->status === Agency::STATUS_ACTIVE
                    ? Worker::STATUS_AVAILABLE
                    : Worker::STATUS_INACTIVE,
            ]);

            // No longer deployed — drop their shift so the Shift page only
            // shows actively deployed workers' shifts.
            $worker->shifts()->detach();
        }

        AgencyActivity::create([
            'agency_id' => $manpowerRequest->agency_id,
            'worker_id' => $assignment->worker_id,
            'request_id' => $manpowerRequest->id,
            'action' => 'worker_released',
            'details' => "Worker {$worker?->code} released from request {$manpowerRequest->code}.",
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', "Worker {$worker?->name} released.");
    }

    /**
     * HR accepts an agency proposal: the worker is assigned exactly as if
     * HR had picked them (same guards), and the proposal is marked Accepted.
     */
    public function acceptProposal(Request $request, ManpowerRequest $manpowerRequest, ManpowerProposal $proposal)
    {
        abort_unless((int) $proposal->request_id === (int) $manpowerRequest->id, 404);

        if ($proposal->status !== ManpowerProposal::STATUS_PROPOSED) {
            return back()->with('error', 'This proposal is no longer pending.');
        }

        // Real data from the WORKER ASSIGNMENT preview (company email + deployment start).
        $data = $request->validate([
            'company_email' => 'nullable|email|max:255',
            'start_date' => 'nullable|date',
        ]);

        $worker = $proposal->worker;
        $refusal = $this->assignmentRefusal($manpowerRequest, $worker);
        if ($refusal) {
            return back()->withErrors(['worker_id' => $refusal]);
        }

        // Same worker-account flow as direct assign (confirm -> preview -> finish).
        [$companyEmail, $tempPassword, $trainingLabel, $mailSent, $recipient, $mailError, $employeeId] =
            $this->finalizeWorkerAccount($manpowerRequest, $worker, $data['company_email'] ?? null, $data['start_date'] ?? null);

        $this->createAssignment($manpowerRequest, $worker->fresh());
        $proposal->update(['status' => ManpowerProposal::STATUS_ACCEPTED, 'reviewed_by_name' => auth()->user()?->name]);

        $msg = "Proposal accepted — {$worker->name} assigned ({$manpowerRequest->filled_count} of {$manpowerRequest->headcount} filled).";
        if ($mailSent) {
            $msg .= " Welcome email sent to {$recipient}.";
        } elseif ($recipient && $mailError) {
            $msg .= ' Welcome email failed to send.';
        } elseif (! $recipient) {
            $msg .= ' No worker email on file — welcome email not sent.';
        }

        return back()->with('success', $msg)->with('worker_credentials', [
            'worker' => $worker->name,
            'worker_code' => $worker->code,
            'employee_id' => $employeeId,
            'company_email' => $companyEmail,
            'password' => $tempPassword,
            'training_start' => $trainingLabel,
            'mailed' => $mailSent,
            'mailed_to' => $mailSent ? $recipient : null,
            'mail_error' => $mailError,
        ]);
    }

    /**
     * Shared worker-account finalization for direct assign + proposal accept:
     * fixed Agency Contract, deployment date, a REAL users-table login (so the
     * emailed company email + temp password actually work at login), and the
     * CREATE-EMPLOYEE-style welcome email (flashed once to HR).
     *
     * @return array{string,?string,string,bool,?string,?string,?string}
     *   [companyEmail, tempPassword|null, trainingLabel, mailed, mailedTo, mailError, employeeId]
     *   tempPassword is null when the login already existed (password unchanged).
     */
    protected function finalizeWorkerAccount(ManpowerRequest $manpowerRequest, Worker $worker, ?string $companyEmail, ?string $startDate): array
    {
        $companyEmail = $companyEmail ?: self::suggestCompanyEmail($worker->name);
        $companyEmail = $this->uniqueCompanyEmail($companyEmail, $worker->name);
        $deploymentDate = $startDate ?: $manpowerRequest->start_date?->toDateString() ?? $worker->availability ?? now()->toDateString();
        $worker->update(array_filter([
            'employment_arrangement' => 'Agency Contract',
            'availability' => $deploymentDate,
        ]));

        // Department module (users.role) — same mapping as Onboarding CREATE EMPLOYEE.
        $validRoles = ['HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'PRO', 'PROJ', 'IT', 'LOG', 'CEO'];
        $deptModel = HrmDepartment::where('name', $manpowerRequest->department)->first();
        $role = $deptModel && in_array(strtoupper((string) $deptModel->category), $validRoles, true)
            ? strtoupper((string) $deptModel->category)
            : (in_array(strtoupper((string) $manpowerRequest->department), $validRoles, true) ? strtoupper((string) $manpowerRequest->department) : 'HRM');

        // REAL login: create the users row so the emailed credentials match records.
        // Existing company email is reused untouched (password unchanged).
        $tempPassword = null;
        $employeeId = null;
        $existing = User::where('email', $companyEmail)->first();
        if ($existing) {
            // Existing login reused as the worker's account: tag it as a
            // worker account and align the identifier with WRK-xxx.
            $employeeId = $worker->code;
            $existing->forceFill([
                'is_active' => true,
                'worker_id' => $worker->id,
                'employee_id' => $worker->code,
            ])->save();
        } else {
            $tempPassword = 'Monti-' . Str::upper(Str::random(6));
            $parts = preg_split('/\s+/', trim((string) $worker->name), -1, PREG_SPLIT_NO_EMPTY) ?: [$worker->name];
            $first = $parts[0];
            $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $first;
            $user = new User();
            $user->forceFill([
                'name' => $worker->name,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $companyEmail,
                'password' => Hash::make($tempPassword),
                'role' => in_array($role, ['CEO'] , true) ? 'HRM' : $role,
                'position' => 'staff',
                'is_active' => true,
                // Agency workers keep the WRK-xxx identity (never EMP-xxx).
                'employee_id' => $worker->code,
                'worker_id' => $worker->id,
                'department' => $manpowerRequest->department,
                'join_date' => $deploymentDate,
                'personal_email' => $worker->email,
                'mobile_number' => $worker->phone,
                'current_address' => $worker->address,
                'company' => 'MontiTextile',
                'work_location' => 'Main Manufacturing Plant',
                'work_schedule' => $manpowerRequest->shift,
                'shift' => $manpowerRequest->shift,
            ])->save();
            $employeeId = $user->employee_id;
        }

        $trainingLabel = \Carbon\Carbon::parse($deploymentDate)->format('F j, Y');
        $recipient = $worker->email;
        $mailSent = false;
        $mailError = null;
        if ($recipient) {
            try {
                Mail::to($recipient)->send(new EmployeeWelcomeMail(
                    $worker->name,
                    $employeeId ?? $worker->code,
                    $manpowerRequest->position_skill,
                    $manpowerRequest->department,
                    $companyEmail,
                    $tempPassword ?? '(unchanged — use your existing password)',
                    $trainingLabel,
                    route('login')
                ));
                $mailSent = true;
            } catch (\Throwable $e) {
                $mailError = $e->getMessage();
                Log::warning('Worker welcome email failed for worker #' . $worker->id . ': ' . $e->getMessage());
            }
        }

        return [$companyEmail, $tempPassword, $trainingLabel, $mailSent, $recipient, $mailError, $employeeId];
    }

    protected function assignmentMessage(ManpowerRequest $manpowerRequest, Worker $worker, bool $mailSent, ?string $recipient, ?string $mailError): string
    {
        $msg = "Worker {$worker->name} assigned ({$manpowerRequest->filled_count} of {$manpowerRequest->headcount} filled).";
        if ($mailSent) {
            $msg .= " Welcome email sent to {$recipient}.";
        } elseif ($recipient && $mailError) {
            $msg .= ' Welcome email failed to send.';
        } elseif (! $recipient) {
            $msg .= ' No worker email on file — welcome email not sent.';
        }

        return $msg;
    }

    protected static function suggestCompanyEmail(?string $name): string
    {
        $parts = array_values(array_filter(preg_split('/[^a-z0-9]+/i', strtolower((string) $name)) ?: []));
        $local = count($parts) > 1 ? $parts[0] . '.' . end($parts) : ($parts[0] ?? 'worker');
        return substr($local, 0, 64) . '@monti.com';
    }

    /**
     * Keep company emails unique: if the suggested address belongs to a
     * different login, suffix it (name2, name3...) instead of taking over.
     */
    protected function uniqueCompanyEmail(string $companyEmail, ?string $workerName): string
    {
        [$local, $domain] = array_pad(explode('@', $companyEmail, 2), 2, 'monti.com');
        $candidate = $companyEmail;
        $i = 1;
        while (User::where('email', $candidate)->exists()) {
            // Same worker re-accepting keeps their address (checked by name match below).
            $owner = User::where('email', $candidate)->first(['name']);
            if ($owner && strcasecmp(trim((string) $owner->name), trim((string) $workerName)) === 0) {
                return $candidate;
            }
            $i++;
            $candidate = substr($local . $i, 0, 64) . '@' . $domain;
            if ($i > 50) break;
        }

        return $candidate;
    }

    public function rejectProposal(Request $request, ManpowerRequest $manpowerRequest, ManpowerProposal $proposal)
    {
        $request->validate(['reason' => 'required|string|max:2000']);
        abort_unless((int) $proposal->request_id === (int) $manpowerRequest->id, 404);

        if ($proposal->status !== ManpowerProposal::STATUS_PROPOSED) {
            return back()->with('error', 'This proposal is no longer pending.');
        }

        $proposal->update([
            'status' => ManpowerProposal::STATUS_REJECTED,
            'review_reason' => $request->reason,
            'reviewed_by_name' => auth()->user()?->name,
        ]);
        $this->log($manpowerRequest, 'proposal_rejected', "Proposal of {$proposal->worker?->name} for {$manpowerRequest->code} rejected.", $request->reason);

        return back()->with('success', 'Proposal rejected.');
    }

    // ------------------------------------------------------------------
    // Payloads
    // ------------------------------------------------------------------
    protected function row(ManpowerRequest $request): array
    {
        return [
            'id' => $request->id,
            'code' => $request->code,
            'department' => $request->department,
            'position_skill' => $request->position_skill,
            'needed' => (int) $request->headcount,
            'filled' => (int) $request->filled_count,
            'agency' => $request->agency?->name,
            'agency_id' => $request->agency_id,
            'start_date' => optional($request->start_date)->toDateString(),
            'status' => $request->effective_status,
            'stored_status' => $request->status,
        ];
    }

    protected function detail(ManpowerRequest $request): array
    {
        $assigned = $request->activeAssignments->map(fn ($a) => [
            'assignment_id' => $a->id,
            'worker_id' => $a->worker_id,
            'worker_code' => $a->worker?->code,
            'worker_name' => $a->worker?->name,
            'position_skill' => $a->worker?->position_skill,
            'worker_status' => $a->worker?->status,
            'daily_rate' => $a->worker?->daily_rate,
            'phone' => $a->worker?->phone,
            'gender' => $a->worker?->gender,
            'clearance_expiry' => optional($a->worker?->clearance_expiry)->toDateString(),
            'clearance_expired' => (bool) ($a->worker?->clearance_expired ?? false),
            'resume_url' => $a->worker?->resume_path ? Storage::url($a->worker->resume_path) : null,
            'resume_name' => $a->worker?->resume_original_name,
            'assigned_at' => optional($a->assigned_at)->format('M d, Y'),
            'assigned_by' => $a->assigned_by_name,
        ])->values()->all();

        // Assign picker: only this agency's Available workers with a live
        // clearance and no other active request. Released workers ARE
        // re-assignable to the same request (only active assignments hide them).
        $available = Worker::where('agency_id', $request->agency_id)
            ->where('status', Worker::STATUS_AVAILABLE)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('manpower_assignments as ma')
                ->whereColumn('ma.worker_id', 'workers.id')->whereNull('ma.released_at'))
            ->orderBy('name')->get(['id', 'code', 'name', 'position_skill', 'status', 'daily_rate', 'phone', 'gender', 'clearance_expiry', 'resume_path', 'resume_original_name', 'emergency_name', 'emergency_relationship', 'emergency_phone', 'availability', 'employment_arrangement', 'proposed_department', 'years_experience'])
            ->filter(fn ($w) => ! $w->clearance_expired)
            ->map(fn ($w) => [
                'id' => $w->id, 'code' => $w->code, 'name' => $w->name,
                'position_skill' => $w->position_skill,
                'worker_status' => $w->status,
                'daily_rate' => $w->daily_rate,
                'phone' => $w->phone,
                'gender' => $w->gender,
                'clearance_expiry' => optional($w->clearance_expiry)->toDateString(),
                'resume_url' => $w->resume_path ? Storage::url($w->resume_path) : null,
                'resume_name' => $w->resume_original_name,
                'emergency_name' => $w->emergency_name,
                'emergency_relationship' => $w->emergency_relationship,
                'emergency_phone' => $w->emergency_phone,
                'availability' => $w->availability,
                'employment_arrangement' => $w->employment_arrangement,
                'proposed_department' => $w->proposed_department,
                'years_experience' => $w->years_experience,
            ])->values()->all();

        $activities = AgencyActivity::where('request_id', $request->id)
            ->latest()->take(20)->get()->map(fn ($a) => [
                'id' => $a->id,
                'date' => optional($a->created_at)->format('M d, Y h:i A'),
                'action' => $a->action,
                'details' => $a->details,
                'actor' => $a->actor_name ?? ('#' . $a->actor_id),
                'reason' => $a->reason,
            ])->values()->all();

        // Department module (users.role) for the display-only preview —
        // same mapping as Onboarding CREATE EMPLOYEE (HrmDepartment.category).
        $validRoles = ['HRM', 'SCM', 'FIN', 'MAN', 'INV', 'ORD', 'WAR', 'CRM', 'ECO', 'PRO', 'PROJ', 'IT', 'LOG'];
        $deptModel = \App\Models\Hrm\HrmDepartment::where('name', $request->department)->first();
        $departmentModule = $deptModel && in_array(strtoupper((string) $deptModel->category), $validRoles, true)
            ? strtoupper((string) $deptModel->category)
            : (in_array(strtoupper((string) $request->department), $validRoles, true) ? strtoupper((string) $request->department) : 'HRM');

        return [
            'id' => $request->id,
            'code' => $request->code,
            'department' => $request->department,
            'department_module' => $departmentModule,
            'position_skill' => $request->position_skill,
            'needed' => (int) $request->headcount,
            'filled' => (int) $request->filled_count,
            'start_date' => optional($request->start_date)->toDateString(),
            'end_date' => optional($request->end_date)->toDateString(),
            'shift' => $request->shift,
            'notes' => $request->notes,
            'agency_id' => $request->agency_id,
            'agency' => $request->agency?->name,
            'agency_code' => $request->agency?->code,
            'agency_status' => $request->agency?->status,
            'status' => $request->effective_status,
            'stored_status' => $request->status,
            'cancel_reason' => $request->cancel_reason,
            'assigned' => $assigned,
            'available_workers' => $available,
            // Attendance summary per worker on this request.
            'attendance_summary' => \App\Models\Hrm\Attendance::with('worker:id,code,name')
                ->where('request_id', $request->id)
                ->get()->groupBy('worker_id')->map(fn ($rows) => [
                    'worker_id' => $rows->first()->worker_id,
                    'worker_code' => $rows->first()->worker?->code,
                    'worker_name' => $rows->first()->worker?->name,
                    'records' => $rows->count(),
                    'present' => $rows->where('status', 'Present')->count(),
                    'absent' => $rows->where('status', 'Absent')->count(),
                    'late' => $rows->where('status', 'Late')->count(),
                    'hours' => round($rows->sum('hours'), 2),
                    'overtime_hours' => round($rows->sum('overtime_hours'), 2),
                ])->values()->all(),
            // Agency proposals awaiting HR review (Proposed → Accepted/Rejected).
            // Worker snapshot included so HR can review (and open) the
            // proposed worker's info without leaving the page.
            'proposals' => ManpowerProposal::with('worker:id,code,name,position_skill,status,daily_rate,phone,gender,clearance_expiry,resume_path,resume_original_name,emergency_name,emergency_relationship,emergency_phone,availability,employment_arrangement,proposed_department,years_experience')
                ->where('request_id', $request->id)
                ->orderByDesc('id')->get()->map(fn ($p) => [
                    'id' => $p->id,
                    'worker_id' => $p->worker_id,
                    'worker_code' => $p->worker?->code,
                    'worker_name' => $p->worker?->name,
                    'position_skill' => $p->worker?->position_skill,
                    'worker_status' => $p->worker?->status,
                    'daily_rate' => $p->worker?->daily_rate,
                    'phone' => $p->worker?->phone,
                    'gender' => $p->worker?->gender,
                    'clearance_expiry' => optional($p->worker?->clearance_expiry)->toDateString(),
                    'clearance_expired' => (bool) ($p->worker?->clearance_expired ?? false),
                    'resume_url' => $p->worker?->resume_path ? Storage::url($p->worker->resume_path) : null,
                    'resume_name' => $p->worker?->resume_original_name,
                    // Extra display-only fields for the CREATE-EMPLOYEE-style preview modal (no DB write).
                    'emergency_name' => $p->worker?->emergency_name,
                    'emergency_relationship' => $p->worker?->emergency_relationship,
                    'emergency_phone' => $p->worker?->emergency_phone,
                    'availability' => $p->worker?->availability,
                    'employment_arrangement' => $p->worker?->employment_arrangement,
                    'proposed_department' => $p->worker?->proposed_department,
                    'years_experience' => $p->worker?->years_experience,
                    'status' => $p->status,
                    'proposed_by' => $p->proposed_by_name,
                    'proposed_at' => optional($p->created_at)->format('M d, Y'),
                    'review_reason' => $p->review_reason,
                    'reviewed_by' => $p->reviewed_by_name,
                ])->values()->all(),
            'activities' => $activities,
        ];
    }

    protected function rules(?int $ignoreId = null): array
    {
        return [
            'code' => ['nullable', 'string', 'max:30', Rule::unique('manpower_requests', 'code')->ignore($ignoreId)],
            'department' => 'required|string|max:255',
            'position_skill' => 'required|string|max:255',
            'headcount' => 'required|integer|min:1|max:10000',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'shift' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'agency_id' => 'required|exists:agencies,id',
            'submit' => 'nullable|boolean',
        ];
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        if ($request->get('code') === '') {
            $request->merge(['code' => null]);
        }

        $data = $request->validate($this->rules($ignoreId));

        if (! empty($data['start_date']) && ! empty($data['end_date'])
            && $data['end_date'] < $data['start_date']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'end_date' => 'End date cannot be before start date.',
            ]);
        }

        return $data;
    }

    protected static function nextCode(): string
    {
        $next = (int) (ManpowerRequest::max('id') ?? 0) + 1;
        do {
            $code = 'REQ-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (ManpowerRequest::where('code', $code)->exists());

        return $code;
    }

    protected function log(ManpowerRequest $request, string $action, ?string $details, ?string $reason): void
    {
        AgencyActivity::create([
            'agency_id' => $request->agency_id,
            'request_id' => $request->id,
            'action' => $action,
            'details' => $details,
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
            'reason' => $reason,
        ]);
    }
}
