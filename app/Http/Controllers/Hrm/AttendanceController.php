<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Agency;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\Attendance;
use App\Models\Hrm\AttendanceAdjustment;
use App\Models\Hrm\ManpowerRequest;
use App\Models\Hrm\Worker;
use App\Support\AttendanceCalc;
use App\Traits\HasPagePermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Agency attendance (HRM > Attendance, Pages 11-13).
 *
 * Lifecycle: Draft → Approved (locked, rate snapshotted) or Draft →
 * Rejected → Draft after correction. Approved rows are corrected only via
 * an Adjustment carrying a reason. Agency disputes (3-day window) are
 * resolved here: adjust, or reject the dispute.
 */
class AttendanceController extends Controller
{
    use HasPagePermissions;

    public const STATUSES = ['Present', 'Absent', 'Late', 'Half day', 'Leave'];

    // ------------------------------------------------------------------
    // Page 11: Attendance list
    // ------------------------------------------------------------------
    public function index(Request $request)
    {
        $q = Attendance::with([
            'worker:id,code,name,agency_id',
            'worker.agency:id,code,name',
            'request:id,code,department',
        ]);

        if ($from = $request->get('from')) {
            $q->whereDate('date', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $q->whereDate('date', '<=', $to);
        }
        if ($agencyId = $request->get('agency_id')) {
            $q->whereHas('worker', fn ($w) => $w->where('agency_id', $agencyId));
        }
        if ($requestId = $request->get('request_id')) {
            $q->where('request_id', $requestId);
        }
        if ($workerId = $request->get('worker_id')) {
            $q->where('worker_id', $workerId);
        }
        if (($status = $request->get('status')) && $status !== 'All') {
            $q->where('status', $status);
        }
        if (($approval = $request->get('approval')) && $approval !== 'All') {
            $q->where('approval', $approval);
        }

        $paginated = $q->orderByDesc('date')->orderByDesc('id')->paginate(15)->withQueryString();
        $rows = collect($paginated->items())->map(fn ($a) => $this->row($a))->values()->all();

        return Inertia::render('Dashboard/HRM_NEW/Attendance/Index', [
            'records' => $rows,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'stats' => [
                'draft' => Attendance::where('approval', Attendance::APPROVAL_DRAFT)->count(),
                'approved' => Attendance::where('approval', Attendance::APPROVAL_APPROVED)->count(),
                'disputed' => Attendance::where('agency_confirmation', 'Disputed')->count(),
            ],
            'agencies' => Agency::orderBy('name')->get(['id', 'code', 'name', 'status']),
            'filters' => $request->only(['from', 'to', 'agency_id', 'request_id', 'worker_id', 'status', 'approval']),
            // Add-record modal: open requests with their actively assigned workers.
            'assignable' => ManpowerRequest::with('activeAssignments.worker:id,code,name')
                ->where('status', ManpowerRequest::STATUS_OPEN)
                ->orderByDesc('id')->take(200)->get(['id', 'code', 'department'])
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'label' => "{$r->code} · {$r->department}",
                    'workers' => $r->activeAssignments->map(fn ($a) => [
                        'id' => $a->worker_id,
                        'label' => ($a->worker?->name ?? '') . ' (' . ($a->worker?->code ?? '') . ')',
                    ])->values()->all(),
                ])->values()->all(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    // ------------------------------------------------------------------
    // Page 12: Daily / bulk entry sheet
    // ------------------------------------------------------------------
    public function bulk(Request $request)
    {
        $requestId = $request->get('request_id');
        $date = $request->get('date', today()->toDateString());

        $manpower = $requestId ? ManpowerRequest::with('agency:id,code,name,status')->find($requestId) : null;

        $sheet = [];
        if ($manpower) {
            $sheet = $manpower->activeAssignments()->with('worker:id,code,name,position_skill')->get()
                ->map(function ($asg) use ($date) {
                    $existing = Attendance::where('worker_id', $asg->worker_id)
                        ->whereDate('date', $date)->first();
                    return [
                        'worker_id' => $asg->worker_id,
                        'worker_code' => $asg->worker?->code,
                        'worker_name' => $asg->worker?->name,
                        'position_skill' => $asg->worker?->position_skill,
                        'record_id' => $existing?->id,
                        'locked' => $existing && $existing->approval === Attendance::APPROVAL_APPROVED,
                        'status' => $existing?->status ?? 'Present',
                        'time_in' => $existing?->time_in,
                        'time_out' => $existing?->time_out,
                        'overtime_hours' => $existing?->overtime_hours ?? 0,
                        'remarks' => $existing?->remarks,
                    ];
                })->values()->all();
        }

        return Inertia::render('Dashboard/HRM_NEW/Attendance/Bulk', [
            'requests' => ManpowerRequest::with('agency:id,code,name')
                ->where('status', ManpowerRequest::STATUS_OPEN)
                ->orderByDesc('id')->take(200)->get(['id', 'code', 'department', 'position_skill', 'agency_id'])
                ->map(fn ($r) => [
                    'id' => $r->id, 'code' => $r->code,
                    'label' => "{$r->code} · {$r->department} · {$r->position_skill}",
                    'agency' => $r->agency?->name,
                ])->values()->all(),
            'request_id' => $requestId ? (int) $requestId : null,
            'request' => $manpower ? [
                'id' => $manpower->id, 'code' => $manpower->code,
                'department' => $manpower->department,
                'agency' => $manpower->agency?->name,
                'agency_status' => $manpower->agency?->status,
                'start_date' => optional($manpower->start_date)->toDateString(),
                'end_date' => optional($manpower->end_date)->toDateString(),
            ] : null,
            'date' => $date,
            'sheet' => $sheet,
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'request_id' => 'required|exists:manpower_requests,id',
            'date' => 'required|date',
            'submit' => 'nullable|boolean',
            'rows' => 'required|array|min:1',
            'rows.*.worker_id' => 'required|exists:workers,id',
            'rows.*.status' => 'required|in:' . implode(',', self::STATUSES),
            'rows.*.time_in' => 'nullable|string|max:5',
            'rows.*.time_out' => 'nullable|string|max:5',
            'rows.*.overtime_hours' => 'nullable|numeric|min:0|max:24',
            'rows.*.remarks' => 'nullable|string|max:1000',
        ]);

        $manpower = ManpowerRequest::with('agency')->findOrFail($data['request_id']);
        $this->assertWritableDate($manpower, $data['date']);

        foreach ($data['rows'] as $row) {
            $worker = Worker::findOrFail($row['worker_id']);
            $this->assertRowEligible($manpower, $worker);

            $existing = Attendance::where('worker_id', $worker->id)->whereDate('date', $data['date'])->first();
            if ($existing && $existing->approval === Attendance::APPROVAL_APPROVED) {
                continue; // locked rows are corrected via Adjustment, not bulk
            }
            if ($existing && $existing->request_id !== (int) $manpower->id) {
                throw ValidationException::withMessages([
                    'date' => "A record already exists for {$worker->name} on {$data['date']}.",
                ]);
            }

            $this->assertTimes($row['time_in'] ?? null, $row['time_out'] ?? null);

            $payload = [
                'worker_id' => $worker->id,
                'request_id' => $manpower->id,
                'date' => $data['date'],
                'status' => $row['status'],
                'time_in' => $row['time_in'] ?? null,
                'time_out' => $row['time_out'] ?? null,
                'hours' => AttendanceCalc::hours($row['time_in'] ?? null, $row['time_out'] ?? null),
                'overtime_hours' => $row['overtime_hours'] ?? 0,
                'remarks' => $row['remarks'] ?? null,
                'approval' => Attendance::APPROVAL_DRAFT,
            ];

            if ($existing) {
                $existing->update($payload);
                $this->log($manpower->agency_id, $worker->id, $manpower->id, 'attendance_updated', "Attendance for {$worker->name} on {$data['date']} updated (Draft).", null);
            } else {
                try {
                    $record = Attendance::create($payload);
                } catch (\Illuminate\Database\QueryException $e) {
                    throw ValidationException::withMessages([
                        'date' => "A record already exists for {$worker->name} on {$data['date']} (one record per worker per day).",
                    ]);
                }
                $this->log($manpower->agency_id, $worker->id, $manpower->id, 'attendance_recorded', "Attendance for {$worker->name} on {$data['date']} recorded (Draft).", null);
            }
        }

        $msg = $data['submit'] ?? false
            ? 'Sheet saved and submitted for approval.'
            : 'Sheet saved as draft.';

        return back()->with('success', $msg);
    }

    /** Single Add-record (modal on the list page). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'request_id' => 'required|exists:manpower_requests,id',
            'worker_id' => 'required|exists:workers,id',
            'date' => 'required|date',
            'status' => 'required|in:' . implode(',', self::STATUSES),
            'time_in' => 'nullable|string|max:5',
            'time_out' => 'nullable|string|max:5',
            'overtime_hours' => 'nullable|numeric|min:0|max:24',
            'remarks' => 'nullable|string|max:1000',
            'submit' => 'nullable|boolean',
        ]);

        $manpower = ManpowerRequest::with('agency')->findOrFail($data['request_id']);
        $worker = Worker::findOrFail($data['worker_id']);

        $this->assertWritableDate($manpower, $data['date']);
        $this->assertRowEligible($manpower, $worker);

        if (Attendance::where('worker_id', $worker->id)->whereDate('date', $data['date'])->exists()) {
            throw ValidationException::withMessages([
                'date' => "A record already exists for {$worker->name} on {$data['date']} (one record per worker per day).",
            ]);
        }
        $this->assertTimes($data['time_in'] ?? null, $data['time_out'] ?? null);

        Attendance::create([
            'worker_id' => $worker->id,
            'request_id' => $manpower->id,
            'date' => $data['date'],
            'status' => $data['status'],
            'time_in' => $data['time_in'] ?? null,
            'time_out' => $data['time_out'] ?? null,
            'hours' => AttendanceCalc::hours($data['time_in'] ?? null, $data['time_out'] ?? null),
            'overtime_hours' => $data['overtime_hours'] ?? 0,
            'remarks' => $data['remarks'] ?? null,
            'approval' => Attendance::APPROVAL_DRAFT,
        ]);
        $this->log($manpower->agency_id, $worker->id, $manpower->id, 'attendance_recorded', "Attendance for {$worker->name} on {$data['date']} recorded (Draft).", null);

        return back()->with('success', ($data['submit'] ?? false)
            ? 'Record saved and submitted for approval.'
            : 'Attendance record saved as draft.');
    }

    /** Edit a Draft (stays Draft) or correct a Rejected row (back to Draft). */
    public function update(Request $request, Attendance $attendance)
    {
        if ($attendance->approval === Attendance::APPROVAL_APPROVED) {
            return back()->with('error', 'Approved records are locked. Use an Adjustment with a reason to correct them.');
        }

        $data = $request->validate([
            'status' => 'sometimes|in:' . implode(',', self::STATUSES),
            'time_in' => 'nullable|string|max:5',
            'time_out' => 'nullable|string|max:5',
            'overtime_hours' => 'nullable|numeric|min:0|max:24',
            'remarks' => 'nullable|string|max:1000',
            'date' => 'sometimes|date',
        ]);

        $manpower = $attendance->request;
        if (! empty($data['date'])) {
            $this->assertWritableDate($manpower, $data['date']);
            if (Attendance::where('worker_id', $attendance->worker_id)
                ->whereDate('date', $data['date'])->where('id', '!=', $attendance->id)->exists()) {
                throw ValidationException::withMessages([
                    'date' => 'A record already exists for this worker on that date.',
                ]);
            }
        }
        $this->assertTimes(
            $data['time_in'] ?? $attendance->time_in,
            $data['time_out'] ?? $attendance->time_out
        );

        $attendance->update($data + [
            'hours' => AttendanceCalc::hours(
                $data['time_in'] ?? $attendance->time_in,
                $data['time_out'] ?? $attendance->time_out
            ),
            // A corrected Rejected row re-enters the approval queue.
            'approval' => Attendance::APPROVAL_DRAFT,
            'review_reason' => null,
        ]);
        $this->log($attendance->worker->agency_id, $attendance->worker_id, $attendance->request_id, 'attendance_updated', "Attendance for {$attendance->worker->name} on {$attendance->date->toDateString()} updated (Draft).", null);

        return back()->with('success', 'Attendance record updated.');
    }

    // ------------------------------------------------------------------
    // Page 13: Approval queue (+ disputes tab data)
    // ------------------------------------------------------------------
    public function approval(Request $request)
    {
        $drafts = Attendance::with(['worker:id,code,name,agency_id', 'worker.agency:id,code,name', 'request:id,code,department'])
            ->where('approval', Attendance::APPROVAL_DRAFT)
            ->orderBy('date')->get();

        $groups = $drafts->groupBy(fn ($a) => $a->request_id . '|' . $a->date->toDateString())
            ->map(fn ($rows, $key) => [
                'request_id' => $rows->first()->request_id,
                'request_code' => $rows->first()->request?->code,
                'department' => $rows->first()->request?->department,
                'agency' => $rows->first()->worker?->agency?->name,
                'date' => $rows->first()->date->toDateString(),
                'rows' => $rows->map(fn ($a) => $this->row($a))->values()->all(),
            ])->values()->all();

        $disputes = Attendance::with(['worker:id,code,name,agency_id', 'worker.agency:id,code,name', 'request:id,code,department'])
            ->where('agency_confirmation', 'Disputed')
            ->orderByDesc('disputed_at')->take(100)->get()
            ->map(fn ($a) => $this->row($a))->values()->all();

        return Inertia::render('Dashboard/HRM_NEW/Attendance/Approval', [
            'groups' => $groups,
            'disputes' => $disputes,
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function approve(Attendance $attendance)
    {
        if ($attendance->approval !== Attendance::APPROVAL_DRAFT) {
            return back()->with('error', 'Only Draft records can be approved.');
        }

        $attendance->update([
            'approval' => Attendance::APPROVAL_APPROVED,
            'approved_at' => now(),
            // Snapshot the rate now — later rate changes never alter billing.
            'rate_snapshot' => $attendance->worker->daily_rate,
            'review_reason' => null,
        ]);
        $this->log($attendance->worker->agency_id, $attendance->worker_id, $attendance->request_id, 'attendance_approved', "Attendance for {$attendance->worker->name} on {$attendance->date->toDateString()} approved (locked).", null);

        return back()->with('success', 'Attendance approved and locked.');
    }

    public function approveAll(Request $request)
    {
        $data = $request->validate([
            'request_id' => 'required|exists:manpower_requests,id',
            'date' => 'nullable|date',
        ]);

        $q = Attendance::where('approval', Attendance::APPROVAL_DRAFT)
            ->where('request_id', $data['request_id']);
        if (! empty($data['date'])) {
            $q->whereDate('date', $data['date']);
        }

        $count = 0;
        foreach ($q->with('worker:id,daily_rate')->get() as $record) {
            $record->update([
                'approval' => Attendance::APPROVAL_APPROVED,
                'approved_at' => now(),
                'rate_snapshot' => $record->worker->daily_rate,
                'review_reason' => null,
            ]);
            $count++;
        }

        if ($count === 0) {
            return back()->with('error', 'No Draft records to approve.');
        }

        return back()->with('success', "{$count} attendance record(s) approved and locked.");
    }

    public function reject(Request $request, Attendance $attendance)
    {
        $request->validate(['reason' => 'required|string|max:2000']);

        if ($attendance->approval !== Attendance::APPROVAL_DRAFT) {
            return back()->with('error', 'Only Draft records can be rejected.');
        }

        $attendance->update(['approval' => Attendance::APPROVAL_REJECTED, 'review_reason' => $request->reason]);
        $this->log($attendance->worker->agency_id, $attendance->worker_id, $attendance->request_id, 'attendance_rejected', "Attendance for {$attendance->worker->name} on {$attendance->date->toDateString()} rejected.", $request->reason);

        return back()->with('success', 'Attendance rejected. Correct it to return it to Draft.');
    }

    /**
     * Adjustment: reason-gated correction of a locked (Approved) row,
     * written to the adjustments table and the activity trail.
     */
    public function adjust(Request $request, Attendance $attendance)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:2000',
            'status' => 'sometimes|in:' . implode(',', self::STATUSES),
            'time_in' => 'nullable|string|max:5',
            'time_out' => 'nullable|string|max:5',
            'overtime_hours' => 'nullable|numeric|min:0|max:24',
            'remarks' => 'nullable|string|max:1000',
            'date' => 'sometimes|date',
        ]);

        if ($attendance->approval !== Attendance::APPROVAL_APPROVED) {
            return back()->with('error', 'Only Approved (locked) records need an Adjustment.');
        }

        $reason = $data['reason'];
        unset($data['reason']);

        if (isset($data['date'])) {
            $this->assertWritableDate($attendance->request, $data['date']);
            if (Attendance::where('worker_id', $attendance->worker_id)
                ->whereDate('date', $data['date'])->where('id', '!=', $attendance->id)->exists()) {
                throw ValidationException::withMessages([
                    'date' => 'A record already exists for this worker on that date.',
                ]);
            }
        }
        $this->assertTimes(
            $data['time_in'] ?? $attendance->time_in,
            $data['time_out'] ?? $attendance->time_out
        );
        $data['hours'] = AttendanceCalc::hours(
            $data['time_in'] ?? $attendance->time_in,
            $data['time_out'] ?? $attendance->time_out
        );

        $changes = [];
        foreach ($data as $field => $value) {
            $before = $attendance->{$field};
            $before = $before instanceof \DateTimeInterface ? $before->format('Y-m-d') : $before;
            if ((string) $before !== (string) $value) {
                $changes[$field] = ['from' => $before, 'to' => $value];
            }
        }
        if (empty($changes)) {
            return back()->with('error', 'No changes to adjust.');
        }

        $attendance->update($data);
        AttendanceAdjustment::create([
            'attendance_id' => $attendance->id,
            'changes' => $changes,
            'reason' => $reason,
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
        ]);
        // An HR adjustment settles any open agency dispute on the row.
        if ($attendance->agency_confirmation === 'Disputed') {
            $attendance->update(['agency_confirmation' => 'Confirmed']);
        }
        $this->log($attendance->worker->agency_id, $attendance->worker_id, $attendance->request_id, 'attendance_adjusted', "Attendance for {$attendance->worker->name} on {$attendance->date->toDateString()} adjusted (" . count($changes) . " field(s)).", $reason);

        return back()->with('success', 'Adjustment applied and written to the trail.');
    }

    /** HR rejects an agency dispute: the record stands, marked Confirmed. */
    public function rejectDispute(Request $request, Attendance $attendance)
    {
        $request->validate(['reason' => 'required|string|max:2000']);

        if ($attendance->agency_confirmation !== 'Disputed') {
            return back()->with('error', 'This record has no open dispute.');
        }

        $attendance->update(['agency_confirmation' => 'Confirmed']);
        $this->log($attendance->worker->agency_id, $attendance->worker_id, $attendance->request_id, 'dispute_rejected', "Dispute on {$attendance->worker->name} ({$attendance->date->toDateString()}) rejected — record stands.", $request->reason);

        return back()->with('success', 'Dispute rejected. The record stands as Confirmed.');
    }

    // ------------------------------------------------------------------
    // Guards + payloads
    // ------------------------------------------------------------------
    protected function row(Attendance $a): array
    {
        return [
            'id' => $a->id,
            'date' => $a->date->toDateString(),
            'worker_id' => $a->worker_id,
            'worker_code' => $a->worker?->code,
            'worker_name' => $a->worker?->name,
            'agency' => $a->worker?->agency?->name,
            'request_id' => $a->request_id,
            'request_code' => $a->request?->code,
            'department' => $a->request?->department,
            'time_in' => $a->time_in,
            'time_out' => $a->time_out,
            'hours' => $a->hours,
            'overtime_hours' => $a->overtime_hours,
            'status' => $a->status,
            'approval' => $a->approval,
            'review_reason' => $a->review_reason,
            'confirmation' => $a->display_confirmation,
            'confirmable' => (bool) $a->confirmable,
            'dispute_reason' => $a->dispute_reason,
            'rate_snapshot' => $a->rate_snapshot,
            'remarks' => $a->remarks,
            'billed' => $a->billed_statement_id !== null,
        ];
    }

    /**
     * New attendance is refused when the agency is terminated AND a final
     * (Paid/Cancelled) statement already exists. Otherwise work already
     * done — even for suspended/terminated agencies — stays recordable.
     */
    protected function assertWritableDate(ManpowerRequest $request, string $date): void
    {
        $agency = $request->agency;

        if ($agency->status === Agency::STATUS_TERMINATED && $agency->has_final_statement) {
            throw ValidationException::withMessages([
                'date' => 'The final statement for this terminated agency is closed — nothing new can be added.',
            ]);
        }

        // Final work for terminated agencies is only allowed on/before the
        // termination date.
        if ($agency->status === Agency::STATUS_TERMINATED && $agency->terminated_at) {
            if ($date > $agency->terminated_at->toDateString()) {
                throw ValidationException::withMessages([
                    'date' => 'Only work done on or before the termination date (' . $agency->terminated_at->toDateString() . ') can still be recorded.',
                ]);
            }
        }

        if ($request->start_date && $date < $request->start_date->toDateString()) {
            throw ValidationException::withMessages([
                'date' => 'Date is before the request start date (' . $request->start_date->toDateString() . ').',
            ]);
        }
        if ($request->end_date && $date > $request->end_date->toDateString()) {
            throw ValidationException::withMessages([
                'date' => 'Date is after the request end date (' . $request->end_date->toDateString() . ').',
            ]);
        }
    }

    protected function assertRowEligible(ManpowerRequest $request, Worker $worker): void
    {
        if ((int) $worker->agency_id !== (int) $request->agency_id) {
            throw ValidationException::withMessages([
                'worker_id' => 'This worker does not belong to the request’s agency.',
            ]);
        }
        $active = $worker->activeAssignment()->where('request_id', $request->id)->exists();
        if (! $active) {
            throw ValidationException::withMessages([
                'worker_id' => 'Only workers with an active assignment on this request can be recorded.',
            ]);
        }
    }

    protected function assertTimes(?string $in, ?string $out): void
    {
        foreach (['time_in' => $in, 'time_out' => $out] as $field => $value) {
            if ($value !== null && $value !== '' && ! preg_match('/^\d{2}:\d{2}$/', $value)) {
                throw ValidationException::withMessages([$field => 'Use HH:MM (24-hour) format.']);
            }
        }
        // Time out cannot be before time in on the same day.
        if ($in && $out && strcmp($out, $in) < 0) {
            throw ValidationException::withMessages([
                'time_out' => 'Time out cannot be before time in.',
            ]);
        }
    }

    protected function log(int $agencyId, int $workerId, int $requestId, string $action, ?string $details, ?string $reason): void
    {
        AgencyActivity::create([
            'agency_id' => $agencyId,
            'worker_id' => $workerId,
            'request_id' => $requestId,
            'action' => $action,
            'details' => $details,
            'actor_id' => auth()->id(),
            'actor_name' => auth()->user()?->name,
            'reason' => $reason,
        ]);
    }
}
