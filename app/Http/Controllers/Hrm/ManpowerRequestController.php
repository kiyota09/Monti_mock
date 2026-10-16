<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Agency;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\ManpowerAssignment;
use App\Models\Hrm\ManpowerProposal;
use App\Models\Hrm\ManpowerRequest;
use App\Models\Hrm\Worker;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
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
        $manpowerRequest->loadMissing(['agency:id,code,name,status', 'activeAssignments.worker:id,code,name,position_skill,agency_id']);

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
        $data = $request->validate(['worker_id' => 'required|exists:workers,id']);
        $worker = Worker::findOrFail($data['worker_id']);

        if ($refusal = $this->assignmentRefusal($manpowerRequest, $worker)) {
            return back()->withErrors(['worker_id' => $refusal]);
        }

        $this->createAssignment($manpowerRequest, $worker);

        return back()->with('success', "Worker {$worker->name} assigned ({$manpowerRequest->filled_count} of {$manpowerRequest->headcount} filled).");
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

    public function release(ManpowerRequest $manpowerRequest, ManpowerAssignment $assignment)    {
        abort_unless((int) $assignment->request_id === (int) $manpowerRequest->id, 404);
        if ($assignment->released_at !== null) {
            return back()->with('error', 'This assignment was already released.');
        }

        $assignment->update(['released_at' => now()]);

        $worker = $assignment->worker;
        if ($worker) {
            // Released workers become Available again — unless their agency
            // is no longer Active, in which case they go Inactive.
            $worker->update([
                'status' => $worker->agency->status === Agency::STATUS_ACTIVE
                    ? Worker::STATUS_AVAILABLE
                    : Worker::STATUS_INACTIVE,
            ]);
        }

        AgencyActivity::create([
            'agency_id' => $manpowerRequest->agency_id,
            'worker_id' => $assignment->worker_id,
            'request_id' => $manpowerRequest->id,
            'action' => 'worker_released',
            'details' => "Worker {$worker?->code} released from request {$manpowerRequest->code}.",
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
        ]);

        return back()->with('success', "Worker {$worker?->name} released.");
    }

    /**
     * HR accepts an agency proposal: the worker is assigned exactly as if
     * HR had picked them (same guards), and the proposal is marked Accepted.
     */
    public function acceptProposal(ManpowerRequest $manpowerRequest, ManpowerProposal $proposal)
    {
        abort_unless((int) $proposal->request_id === (int) $manpowerRequest->id, 404);

        if ($proposal->status !== ManpowerProposal::STATUS_PROPOSED) {
            return back()->with('error', 'This proposal is no longer pending.');
        }

        $worker = $proposal->worker;
        $refusal = $this->assignmentRefusal($manpowerRequest, $worker);
        if ($refusal) {
            return back()->withErrors(['worker_id' => $refusal]);
        }

        $this->createAssignment($manpowerRequest, $worker);
        $proposal->update(['status' => ManpowerProposal::STATUS_ACCEPTED, 'reviewed_by_name' => auth()->user()?->name]);

        return back()->with('success', "Proposal accepted — {$worker->name} assigned ({$manpowerRequest->filled_count} of {$manpowerRequest->headcount} filled).");
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
            'assigned_at' => optional($a->assigned_at)->format('M d, Y'),
            'assigned_by' => $a->assigned_by_name,
        ])->values()->all();

        // Assign picker: only this agency's Available workers with a live
        // clearance and no other active request.
        $available = Worker::where('agency_id', $request->agency_id)
            ->where('status', Worker::STATUS_AVAILABLE)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('manpower_assignments as ma')
                ->whereColumn('ma.worker_id', 'workers.id')->whereNull('ma.released_at'))
            ->orderBy('name')->get(['id', 'code', 'name', 'position_skill', 'clearance_expiry'])
            ->filter(fn ($w) => ! $w->clearance_expired)
            ->map(fn ($w) => [
                'id' => $w->id, 'code' => $w->code, 'name' => $w->name,
                'position_skill' => $w->position_skill,
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

        return [
            'id' => $request->id,
            'code' => $request->code,
            'department' => $request->department,
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
            'proposals' => ManpowerProposal::with('worker:id,code,name,position_skill,status,daily_rate,phone,gender,clearance_expiry')
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
