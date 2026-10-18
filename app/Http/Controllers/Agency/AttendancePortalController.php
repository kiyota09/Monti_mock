<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\AgencyActivity;
use App\Models\Hrm\Attendance;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\ManpowerAssignment;
use App\Models\Hrm\ManpowerRequest;
use App\Models\Hrm\Worker;
use App\Support\AttendanceCalc;
use App\Support\NotifiesHr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Agency portal: read-only attendance for the agency's workers, plus
 * Confirm / Dispute (reason required) on Approved rows inside the 3-day
 * window. Disputes go back to HR.
 */
class AttendancePortalController extends Controller
{
    private function agencyId(Request $request): int
    {
        return (int) $request->user('agency')->agency_id;
    }

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

    public function index(Request $request)
    {
        $agencyId = $this->agencyId($request);

        $date = $request->get('date', today()->toDateString());
        $department = trim((string) $request->get('department', ''));

        // Read-only attendance sheet for this agency's workers. Time in/out
        // comes from their live attendance_logs (employee clock); saved
        // attendance rows fill in workers without a login (e.g. released).
        $date = is_string($date) ? $date : today()->toDateString();
        $workerIds = Worker::where('agency_id', $agencyId)->pluck('id')->all();

        $assignments = ManpowerAssignment::with('request:id,department,shift,status')
            ->whereNull('released_at')
            ->whereIn('worker_id', $workerIds)
            ->get()->keyBy('worker_id');
        $existingByWorker = Attendance::whereDate('date', $date)
            ->whereIn('worker_id', $workerIds)
            ->get()->keyBy('worker_id');

        $workerUsers = Worker::where('agency_id', $agencyId)->whereNotNull('email')
            ->get(['id', 'name', 'email', 'position_skill', 'proposed_department', 'created_at']);

        // Worker -> user login: same personal_email, tie-broken by name.
        $candidateUsers = User::whereNotNull('personal_email')->get(['id', 'name', 'personal_email', 'join_date', 'created_at'])
            ->groupBy(fn ($u) => strtolower((string) $u->personal_email));
        $workerIdToUserId = [];
        $deploymentStartByWorker = [];
        $asDate = fn ($v) => $v ? substr((string) ($v instanceof \Carbon\Carbon ? $v->toDateString() : $v), 0, 10) : null;
        foreach ($workerUsers as $w) {
            $candidates = $candidateUsers->get(strtolower((string) $w->email), collect());
            $match = $candidates->first(fn ($u) => strtolower(trim((string) $u->name)) === strtolower(trim((string) $w->name)))
                ?: ($candidates->count() === 1 ? $candidates->first() : null);
            if ($match) {
                $workerIdToUserId[$w->id] = $match->id;
                $deploymentStartByWorker[$w->id] = $asDate($match->join_date) ?: $asDate($match->created_at);
            } else {
                $deploymentStartByWorker[$w->id] = $asDate($w->created_at);
            }
        }
        $logsByUser = $workerIdToUserId
            ? AttendanceLog::whereIn('user_id', array_values($workerIdToUserId))->whereDate('date', $date)->get()->keyBy('user_id')
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
            $start = $deploymentStartByWorker[$w->id] ?? null;
            if ($start && $date < $start) {
                continue;
            }
            $asg = $assignments->get($w->id);
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

            $rows[] = [
                'worker_id' => $w->id,
                'worker_name' => $w->name,
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

        $departments = ManpowerRequest::where('agency_id', $agencyId)
            ->distinct()->orderBy('department')->pluck('department')->filter()
            ->merge(Worker::where('agency_id', $agencyId)->whereNotNull('proposed_department')
                ->distinct()->orderBy('proposed_department')->pluck('proposed_department')->filter())
            ->unique()->values()->all();

        return Inertia::render('Agency/Attendance/Index', [
            'departments' => $departments,
            'department' => $department,
            'date' => $date,
            'sheet' => array_values($rows),
        ]);
    }

    public function bulkStore(Request $request)
    {
        $agencyId = $this->agencyId($request);
        $data = $request->validate([
            'request_id' => 'required|exists:manpower_requests,id',
            'date' => 'required|date',
            'submit' => 'nullable|boolean',
            'rows' => 'required|array|min:1',
            'rows.*.worker_id' => 'required|exists:workers,id',
            'rows.*.status' => 'required|in:Present,Absent,Late,Half day,Leave',
            'rows.*.time_in' => 'nullable|string|max:5',
            'rows.*.time_out' => 'nullable|string|max:5',
            'rows.*.overtime_hours' => 'nullable|numeric|min:0|max:24',
            'rows.*.remarks' => 'nullable|string|max:1000',
        ]);

        $manpower = ManpowerRequest::where('agency_id', $agencyId)->findOrFail($data['request_id']);
        if ($manpower->status !== ManpowerRequest::STATUS_OPEN) {
            throw ValidationException::withMessages(['request_id' => 'Only Open requests can take attendance.']);
        }
        if ($manpower->start_date && $data['date'] < $manpower->start_date->toDateString()) {
            throw ValidationException::withMessages(['date' => 'Date is before the request start date (' . $manpower->start_date->toDateString() . ').']);
        }
        if ($manpower->end_date && $data['date'] > $manpower->end_date->toDateString()) {
            throw ValidationException::withMessages(['date' => 'Date is after the request end date (' . $manpower->end_date->toDateString() . ').']);
        }

        foreach ($data['rows'] as $row) {
            $worker = Worker::where('agency_id', $agencyId)->findOrFail($row['worker_id']);
            if (! $worker->activeAssignment()->where('request_id', $manpower->id)->exists()) {
                throw ValidationException::withMessages(['rows' => "Only workers with an active assignment on {$manpower->code} can be recorded."]);
            }

            $existing = Attendance::where('worker_id', $worker->id)->whereDate('date', $data['date'])->first();
            if ($existing && $existing->approval === Attendance::APPROVAL_APPROVED) {
                continue; // locked rows are corrected via dispute, not bulk
            }
            if ($existing && (int) $existing->request_id !== (int) $manpower->id) {
                throw ValidationException::withMessages(['date' => "A record already exists for {$worker->name} on {$data['date']}."]);
            }

            foreach (['time_in' => $row['time_in'] ?? null, 'time_out' => $row['time_out'] ?? null] as $field => $value) {
                if ($value !== null && $value !== '' && ! preg_match('/^\d{2}:\d{2}$/', $value)) {
                    throw ValidationException::withMessages([$field => 'Use HH:MM (24-hour) format.']);
                }
            }
            if (! empty($row['time_in']) && ! empty($row['time_out']) && strcmp($row['time_out'], $row['time_in']) < 0) {
                throw ValidationException::withMessages(['time_out' => 'Time out cannot be before time in.']);
            }

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
            } else {
                try {
                    $record = Attendance::create($payload);
                } catch (\Illuminate\Database\QueryException $e) {
                    throw ValidationException::withMessages(['date' => "A record already exists for {$worker->name} on {$data['date']} (one record per worker per day)."]);
                }
            }
            AgencyActivity::create([
                'agency_id' => $agencyId,
                'worker_id' => $worker->id,
                'request_id' => $manpower->id,
                'action' => 'attendance_recorded',
                'details' => "Attendance for {$worker->code} on {$data['date']} recorded by {$request->user('agency')->name} (agency, Draft).",
                'actor_id' => null,
                'actor_name' => $request->user('agency')->name . ' (agency)',
            ]);
            // Mirror into Workforce Time & Attendance (attendance_logs) so the
            // employee's own time in/out appears in HRM Workforce Attendance.
            $this->syncWorkforceLog($worker, $data['date'], $row);
        }

        return back()->with('success', ($data['submit'] ?? false)
            ? 'Sheet saved and submitted for approval.'
            : 'Sheet saved as draft.');
    }

    public function confirm(Request $request, Attendance $attendance)
    {
        $this->assertOwn($request, $attendance);

        if (! $attendance->confirmable) {
            return back()->with('error', 'This record can no longer be confirmed.');
        }

        $attendance->update(['agency_confirmation' => 'Confirmed']);
        $this->log($request, $attendance, 'attendance_confirmed', "Attendance for {$attendance->worker->name} on {$attendance->date->toDateString()} confirmed by {$request->user('agency')->name} (agency).", null);

        return back()->with('success', 'Attendance confirmed.');
    }

    public function dispute(Request $request, Attendance $attendance)
    {
        $request->validate(['reason' => 'required|string|max:2000']);
        $this->assertOwn($request, $attendance);

        if (! $attendance->confirmable) {
            return back()->with('error', 'This record can no longer be disputed (3-day window).');
        }

        $attendance->update([
            'agency_confirmation' => 'Disputed',
            'dispute_reason' => $request->reason,
            'disputed_at' => now(),
            'disputed_by_name' => $request->user('agency')->name,
        ]);
        $this->log($request, $attendance, 'attendance_disputed', "Attendance for {$attendance->worker->name} on {$attendance->date->toDateString()} disputed by {$request->user('agency')->name} (agency).", $request->reason);
        NotifiesHr::push(
            "Attendance disputed: {$attendance->worker->name}",
            "{$request->user('agency')->name} disputed {$attendance->date->toDateString()} — {$request->reason}",
            'hrm.attendance.approval'
        );

        return back()->with('success', 'Dispute sent back to HR.');
    }

    protected function assertOwn(Request $request, Attendance $attendance): void
    {
        $attendance->loadMissing('worker:id,agency_id');
        if (! $attendance->worker || (int) $attendance->worker->agency_id !== $this->agencyId($request)) {
            abort(404);
        }
        if ($attendance->approval !== Attendance::APPROVAL_APPROVED) {
            throw ValidationException::withMessages(['record' => 'Only Approved records can be confirmed or disputed.']);
        }
    }

    protected function log(Request $request, Attendance $attendance, string $action, ?string $details, ?string $reason): void
    {
        AgencyActivity::create([
            'agency_id' => $this->agencyId($request),
            'worker_id' => $attendance->worker_id,
            'request_id' => $attendance->request_id,
            'action' => $action,
            'details' => $details,
            'actor_id' => null,
            'actor_name' => $request->user('agency')->name . ' (agency)',
            'reason' => $reason,
        ]);
    }

    /**
     * Mirror a bulk row into Workforce Time & Attendance (attendance_logs)
     * via the worker's linked user login (created at assignment). Time is
     * stored as h:i A like the employee clock so both surfaces match.
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
