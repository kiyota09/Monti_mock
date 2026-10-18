<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\Agency;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\Attendance;
use App\Models\Hrm\AttendanceAdjustment;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\Holiday;
use App\Models\Hrm\ManpowerRequest;
use App\Models\Hrm\Worker;
use App\Support\AttendanceCalc;
use App\Traits\HasPagePermissions;
use Carbon\Carbon;
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

    public const STATUSES = ['Present', 'Absent', 'Late', 'Half day', 'Leave', 'Holiday'];

    /** Normalize a clock time ("06:38 AM" or "06:38") to "06:38". */
    protected static function toHm(?string $t): ?string
    {
        if (! $t) {
            return null;
        }
        if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $t, $m)) {
            $h = (int) $m[1];
            if (strtoupper($m[3]) === 'PM' && $h < 12) {
                $h += 12;
            }
            if (strtoupper($m[3]) === 'AM' && $h === 12) {
                $h = 0;
            }

            return sprintf('%02d:%02d', $h, $m[2]);
        }

        return strlen($t) >= 5 ? substr($t, 0, 5) : $t;
    }

    // ------------------------------------------------------------------
    // Page 11: Attendance list
    // ------------------------------------------------------------------
    public function index(Request $request)
    {
        // List page removed — bulk entry is now the main page.
        return redirect()->route('hrm.attendance.bulk', $request->only(['request_id', 'date']));
    }

    // ------------------------------------------------------------------
    // Page 12: Daily / bulk entry sheet
    // ------------------------------------------------------------------
    public function bulk(Request $request)
    {
        $date = $request->get('date', today()->toDateString());
        $department = trim((string) $request->get('department', ''));

        // BC: old links used request_id — map it to its department.
        if (! $department && $request->get('request_id')) {
            $old = ManpowerRequest::find($request->get('request_id'));
            if ($old) {
                $department = (string) $old->department;
            }
        }

        // Holiday Management link: the selected day's non-working holiday
        // (name + type), if any.
        $holiday = Holiday::nonWorkingMap($date, $date)[$date] ?? null;

        $sheet = [];
        // Normal attendance sheet: every worker-employee (worker with a user
        // login) is listed; time in/out comes straight from their live
        // attendance_logs (My Attendance clock) — no save step needed.
        {
            $assignments = \App\Models\Hrm\ManpowerAssignment::with('request:id,department,shift,status')
                ->whereNull('released_at')->get()->keyBy('worker_id');
            $existingByWorker = Attendance::with('worker.agency:id,name')->whereDate('date', $date)->get()->keyBy('worker_id');

            $workerUsers = Worker::with('agency:id,name')->whereNotNull('email')->get(['id', 'name', 'code', 'email', 'position_skill', 'proposed_department', 'agency_id', 'created_at']);

            // Worker -> user login: same personal_email, tie-broken by name
            // (seed data may reuse one email across workers).
            $candidateUsers = \App\Models\Core\User::whereNotNull('personal_email')->get(['id', 'name', 'personal_email', 'join_date', 'created_at'])
                ->groupBy(fn ($u) => strtolower((string) $u->personal_email));
            $workerIdToUserId = [];
            $userIdsMatched = [];
            $deploymentStartByWorker = [];
            $asDate = fn ($v) => $v ? substr((string) ($v instanceof \Carbon\Carbon ? $v->toDateString() : $v), 0, 10) : null;
            foreach ($workerUsers as $w) {
                $candidates = $candidateUsers->get(strtolower((string) $w->email), collect());
                $match = $candidates->first(fn ($u) => strtolower(trim((string) $u->name)) === strtolower(trim((string) $w->name)))
                    ?: ($candidates->count() === 1 ? $candidates->first() : null);
                if ($match) {
                    $workerIdToUserId[$w->id] = $match->id;
                    $userIdsMatched[$match->id] = true;
                    // Attendance only counts from the deployment date set on
                    // the worker employee account (join_date of the login).
                    $deploymentStartByWorker[$w->id] = $asDate($match->join_date) ?: $asDate($match->created_at);
                } else {
                    $deploymentStartByWorker[$w->id] = $asDate($w->created_at);
                }
            }
            $logsByUser = $userIdsMatched
                ? AttendanceLog::whereIn('user_id', array_keys($userIdsMatched))->whereDate('date', $date)->get()->keyBy('user_id')
                : collect();

            $displayStatus = function (?string $s): string {
                return match (strtolower(trim((string) $s))) {
                    'on-time', 'ontime', 'present' => 'Present',
                    'late' => 'Late',
                    'in progress', 'in_progress', 'working' => 'In Progress',
                    'on leave', 'on_leave', 'on-leave', 'leave' => 'On Leave',
                    default => ucwords(str_replace(['_', '-'], ' ', (string) ($s ?: 'Present'))),
                };
            };

            $rows = [];
            $seen = [];
            foreach ($workerUsers as $w) {
                if (isset($seen[$w->id])) {
                    continue;
                }
                $seen[$w->id] = true;
                // Before their deployment start date the worker is not on the
                // sheet at all (never auto-marked Absent).
                $start = $deploymentStartByWorker[$w->id] ?? null;
                if ($start && $date < $start) {
                    continue;
                }
                $asg = $assignments->get($w->id);
                // Deployed request department first; proposed only as fallback.
                $departmentOf = $asg?->request?->department ?: ($w->proposed_department ?: null);
                if ($department !== '' && (string) $departmentOf !== $department) {
                    continue;
                }
                $uid = $workerIdToUserId[$w->id] ?? null;
                $log = $uid ? $logsByUser->get($uid) : null;
                $existing = $existingByWorker->get($w->id);

                $timeIn = null;
                $timeOut = null;
                $remarks = null;
                $ot = 0;
                if ($log) {
                    $timeIn = self::toHm($log->clock_in);
                    $timeOut = self::toHm($log->clock_out);
                    $remarks = $log->remarks;
                }
                if ($existing) {
                    $timeIn = $timeIn ?: ($existing->time_in ? substr((string) $existing->time_in, 0, 5) : null);
                    $timeOut = $timeOut ?: ($existing->time_out ? substr((string) $existing->time_out, 0, 5) : null);
                    $ot = $ot ?: ($existing->overtime_hours ?? 0);
                    $remarks = $remarks ?: $existing->remarks;
                }
                if ($log) {
                    $status = $displayStatus($log->status);
                    if ($timeIn && ! $timeOut) {
                        $status = $date === now('Asia/Manila')->toDateString() ? 'In Progress' : 'Incomplete';
                    }
                } elseif ($existing) {
                    $status = $existing->status;
                } elseif ($date < now('Asia/Manila')->toDateString()) {
                    $status = 'Absent';
                } else {
                    $status = '—';
                }

                // Holiday Management link: nobody is Absent/blank on a
                // non-working holiday — show it as a Holiday instead.
                if ($holiday && ($status === 'Absent' || $status === '—')) {
                    $status = 'Holiday';
                    $remarks = $remarks ?: $holiday['name'];
                }

                $rows[] = [
                    'worker_id' => $w->id,
                    'worker_name' => $w->name,
                    'worker_code' => $w->code,
                    'agency' => $w->agency?->name,
                    'department' => $departmentOf,
                    'position_skill' => $w->position_skill,
                    'shift' => $asg?->request?->shift ?: 'Morning Shift',
                    'request_id' => $asg?->request_id,
                    'record_id' => $existing?->id,
                    'locked' => (bool) ($existing && $existing->approval === Attendance::APPROVAL_APPROVED),
                    'status' => $status,
                    'time_in' => $timeIn,
                    'time_out' => $timeOut,
                    'overtime_hours' => $ot,
                    'remarks' => $remarks,
                ];
            }

            // Saved attendance rows on this date for workers without a login
            // (e.g. released) still appear below the list.
            foreach ($existingByWorker as $a) {
                if (isset($seen[$a->worker_id])) {
                    continue;
                }
                $start = $deploymentStartByWorker[$a->worker_id] ?? null;
                if ($start && $date < $start) {
                    continue;
                }
                $asg = $assignments->get($a->worker_id);
                if ($department !== '' && (string) ($asg?->request?->department ?? $a->request?->department) !== $department) {
                    continue;
                }
                $seen[$a->worker_id] = true;
                $rows[] = [
                    'worker_id' => $a->worker_id,
                    'worker_name' => $a->worker?->name,
                    'worker_code' => $a->worker?->code,
                    'agency' => $a->worker?->agency?->name,
                    'department' => $asg?->request?->department ?? $a->request?->department,
                    'position_skill' => $a->worker?->position_skill,
                    'shift' => $asg?->request?->shift ?? $a->request?->shift,
                    'request_id' => $a->request_id,
                    'record_id' => $a->id,
                    'locked' => $a->approval === Attendance::APPROVAL_APPROVED,
                    'status' => $a->status,
                    'time_in' => $a->time_in ? substr((string) $a->time_in, 0, 5) : null,
                    'time_out' => $a->time_out ? substr((string) $a->time_out, 0, 5) : null,
                    'overtime_hours' => $a->overtime_hours ?? 0,
                    'remarks' => $a->remarks,
                ];
            }

            usort($rows, fn ($a, $b) => strcmp((string) ($a['worker_name'] ?? ''), (string) ($b['worker_name'] ?? '')));
            $sheet = array_values($rows);
        }

        $departments = ManpowerRequest::where('status', ManpowerRequest::STATUS_OPEN)
            ->distinct()->orderBy('department')->pluck('department')->filter()
            ->merge(Worker::whereNotNull('proposed_department')->distinct()->orderBy('proposed_department')->pluck('proposed_department')->filter())
            ->unique()->values()->all();

        return Inertia::render('Dashboard/HRM_NEW/Attendance/Bulk', [
            'departments' => $departments,
            'department' => $department,
            'date' => $date,
            'sheet' => $sheet,
            'holiday' => $holiday ? ['name' => $holiday['name'], 'type' => $holiday['type']] : null,
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'department' => 'nullable|string|max:255',
            'date' => 'required|date',
            'submit' => 'nullable|boolean',
            'rows' => 'required|array|min:1',
            'rows.*.worker_id' => 'required|exists:workers,id',
            'rows.*.request_id' => 'required|exists:manpower_requests,id',
            'rows.*.status' => 'required|in:' . implode(',', self::STATUSES),
            'rows.*.time_in' => 'nullable|string|max:5',
            'rows.*.time_out' => 'nullable|string|max:5',
            'rows.*.overtime_hours' => 'nullable|numeric|min:0|max:24',
            'rows.*.remarks' => 'nullable|string|max:1000',
        ]);

        foreach ($data['rows'] as $row) {
            $worker = Worker::findOrFail($row['worker_id']);
            $manpower = ManpowerRequest::with('agency')->findOrFail($row['request_id']);
            if (! empty($data['department']) && (string) $manpower->department !== (string) $data['department']) {
                throw ValidationException::withMessages([
                    'department' => 'Row request does not belong to the selected department.',
                ]);
            }
            if ((int) $worker->agency_id !== (int) $manpower->agency_id) {
                throw ValidationException::withMessages([
                    'worker_id' => 'This worker does not belong to the request’s agency.',
                ]);
            }
            $this->assertWritableDate($manpower, $data['date']);

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
            // Mirror into Workforce Time & Attendance (attendance_logs) so the
            // employee's own time in/out appears in HRM Workforce Attendance.
            $this->syncWorkforceLog($worker, $data['date'], $row);
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

    /**
     * Mirror a bulk row into Workforce Time & Attendance (attendance_logs)
     * via the worker's linked user login (created at assignment).
     */
    protected function syncWorkforceLog(Worker $worker, string $date, array $row): void
    {
        $user = null;
        if (! empty($worker->email)) {
            $user = User::where('personal_email', $worker->email)->first()
                ?? User::where('email', $worker->email)->first();
        }
        if (! $user) {
            $user = User::where('name', $worker->name)->orderByDesc('id')->first();
        }
        if (! $user) {
            return;
        }

        $toAmPm = function (?string $t): ?string {
            if (! $t) {
                return null;
            }
            try {
                return Carbon::createFromFormat('H:i', substr($t, 0, 5))->format('h:i A');
            } catch (\Throwable) {
                return null;
            }
        };
        $status = match ($row['status'] ?? 'Present') {
            'Late' => 'Late',
            'Absent' => 'Absent',
            'Half day' => 'Half Day',
            'Leave' => 'On Leave',
            'Holiday' => 'Holiday',
            default => 'On-Time',
        };

        AttendanceLog::updateOrCreate(
            ['user_id' => $user->id, 'date' => $date],
            [
                'clock_in' => $toAmPm($row['time_in'] ?? null),
                'clock_out' => $toAmPm($row['time_out'] ?? null),
                'status' => $status,
                'remarks' => $row['remarks'] ?? null,
            ]
        );
    }
}
