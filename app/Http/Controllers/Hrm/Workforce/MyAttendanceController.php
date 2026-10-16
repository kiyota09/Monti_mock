<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\EmployeeShift;
use App\Models\Hrm\HrmLeaveType;
use App\Models\Hrm\LeaveRequest;
use App\Traits\HasPagePermissions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * My Attendance — the logged-in employee's own attendance workspace.
 * Today clock in/out, monthly summary, full history, and leave requests
 * flowing into the Leave Management workflow.
 */
class MyAttendanceController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        $user = $request->user();
        $today = now('Asia/Manila')->toDateString();

        // ---- Month window (YYYY-MM, defaults to current) ----
        $monthInput = (string) $request->get('month', substr($today, 0, 7));
        try {
            $monthStart = Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth();
        } catch (\Throwable) {
            $monthStart = Carbon::parse(substr($today, 0, 7) . '-01');
            $monthInput = $monthStart->format('Y-m');
        }
        $monthEnd = (clone $monthStart)->endOfMonth();
        $rangeEnd = min($monthEnd->toDateString(), $today);

        $logs = AttendanceLog::where('user_id', $user->id)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()->keyBy(fn ($l) => substr((string) ($l->date instanceof \Carbon\Carbon ? $l->date->toDateString() : $l->date), 0, 10));

        $leaves = LeaveRequest::where('user_id', $user->id)->where('status', 'approved')
            ->where('start_date', '<=', $monthEnd->toDateString())
            ->where('end_date', '>=', $monthStart->toDateString())
            ->get();

        $shifts = EmployeeShift::where('user_id', $user->id)
            ->whereBetween('effective_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()->keyBy(fn ($s) => substr((string) $s->effective_date, 0, 10));

        // Non-working holidays (Holiday Management): never Absent on these days.
        $holidays = \App\Models\Hrm\Holiday::nonWorkingMap($monthStart->toDateString(), $monthEnd->toDateString());

        // ---- Day-by-day rows (1st → today for the current month) ----
        $rows = [];
        if ($monthStart->toDateString() <= $today) {
            for ($d = clone $monthStart; $d->toDateString() <= $rangeEnd; $d->addDay()) {
                $key = $d->toDateString();
                $log = $logs->get($key);
                $leave = $leaves->first(fn ($l) => $key >= substr((string) $l->start_date, 0, 10) && $key <= substr((string) $l->end_date, 0, 10));
                $shift = $shifts->get($key);
                $isSunday = ((int) $d->format('w')) === 0;

                $schedule = $shift ? trim(($shift->shift_type ?? '') . ($shift->schedule_range ? ' (' . $shift->schedule_range . ')' : '')) : '—';

                if ($log) {
                    $status = self::display($log->status);
                    if (! $log->clock_out && $key < $today) {
                        $status = 'Incomplete';
                    } elseif (! $log->clock_out && $key === $today && $log->clock_in) {
                        $status = 'In Progress';
                    }
                    $remark = $log->remarks ?? '';
                } elseif ($leave) {
                    $status = 'On Leave';
                    $remark = self::leaveName($leave->leave_type);
                } elseif (isset($holidays[$key])) {
                    $status = 'Holiday';
                    $remark = $holidays[$key]['name'];
                } elseif ($isSunday) {
                    $status = 'Rest Day';
                    $remark = '';
                } elseif ($key < $today) {
                    $status = 'Absent';
                    $remark = '';
                } else {
                    $status = '—';
                    $remark = '';
                }

                $rows[] = [
                    'date' => $key,
                    'date_label' => $d->format('M j'),
                    'schedule' => $schedule ?: '—',
                    'time_in' => $log?->clock_in ?: '—',
                    'time_out' => $log?->clock_out ?: '—',
                    'hours' => self::hoursLabel($log?->clock_in, $log?->clock_out),
                    'status' => $status,
                    'remark' => $remark ?: '—',
                ];
            }
        }

        // ---- Monthly summary (unfiltered rows) ----
        $countable = array_filter($rows, fn ($r) => $r['status'] !== '—' && $r['status'] !== 'Rest Day' && $r['status'] !== 'Holiday' && $r['status'] !== 'In Progress');
        $count = fn ($s) => count(array_filter($countable, fn ($r) => $r['status'] === $s));
        $summary = [
            'present' => $count('Present'),
            'late' => $count('Late'),
            'absent' => $count('Absent'),
            'on_leave' => $count('On Leave'),
            'incomplete' => $count('Incomplete'),
            'working_days' => count($countable),
        ];

        // ---- Filters (date narrows to one day, status narrows rows) ----
        if (($fd = $request->get('date')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fd)) {
            $rows = array_values(array_filter($rows, fn ($r) => $r['date'] === $fd));
        }
        if (($fs = trim((string) $request->get('status', ''))) !== '') {
            $rows = array_values(array_filter($rows, fn ($r) => strtolower($r['status']) === strtolower($fs)));
        }

        // ---- Today block ----
        $todayLog = $logs->get($today);
        $todayLeave = $leaves->first(fn ($l) => $today >= substr((string) $l->start_date, 0, 10) && $today <= substr((string) $l->end_date, 0, 10));
        $todayShift = $shifts->get($today);
        $todayStatus = $todayLeave ? 'On Leave'
            : ($todayLog ? ($todayLog->clock_out ? self::display($todayLog->status) : ($todayLog->clock_in ? 'In Progress' : '—')) : '—');

        return Inertia::render('Dashboard/HRM_NEW/MyAttendance', [
            'today' => [
                'date' => $today,
                'date_label' => now('Asia/Manila')->format('F j, Y'),
                'time_in' => $todayLog?->clock_in ?: '—',
                'time_out' => $todayLog?->clock_out ?: '—',
                'status' => $todayStatus,
                'leave_type' => $todayLeave ? self::leaveName($todayLeave->leave_type) : null,
                'schedule' => $todayShift ? (trim(($todayShift->shift_type ?? '') . ($todayShift->schedule_range ? ' (' . $todayShift->schedule_range . ')' : '')) ?: '—') : '—',
                'can_clock_in' => ! $todayLog && ! $todayLeave,
                'can_clock_out' => (bool) ($todayLog && $todayLog->clock_in && ! $todayLog->clock_out && ! $todayLeave),
            ],
            'summary' => $summary,
            'history' => array_values($rows),
            'leaveTypes' => HrmLeaveType::where('is_active', true)->orderBy('name')->select('code', 'name')->get(),
            'filters' => ['month' => $monthInput, 'date' => $request->get('date', ''), 'status' => $request->get('status', '')],
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function clockIn(Request $request)
    {
        $user = $request->user();
        $now = now('Asia/Manila');
        $today = $now->toDateString();

        if ($this->onLeave($user->id, $today)) {
            return back()->with('error', 'You are on approved leave today.');
        }
        $log = AttendanceLog::where('user_id', $user->id)->where('date', $today)->first();
        if ($log && $log->clock_in && ! $log->clock_out) {
            return back()->with('error', 'Already timed in. Please time out first.');
        }
        if ($log && $log->clock_out) {
            return back()->with('error', 'Attendance already completed for today.');
        }

        $shift = EmployeeShift::where('user_id', $user->id)->where('effective_date', $today)->first();
        if (! $shift) {
            $shift = EmployeeShift::firstOrCreate(
                ['user_id' => $user->id, 'effective_date' => $today],
                ['shift_type' => 'Morning', 'schedule_range' => '8AM - 5PM']
            );
        }

        $late = false;
        try {
            $late = $now->gt(Carbon::parse($today.' '.self::shiftStartFor($shift), 'Asia/Manila'));
        } catch (\Throwable) {
        }

        AttendanceLog::create([
            'user_id' => $user->id,
            'date' => $today,
            'clock_in' => $now->format('h:i A'),
            'status' => $late ? 'Late' : 'On-Time',
        ]);

        return back()->with('message', 'Time in recorded at ' . $now->format('h:i A'));
    }

    public function clockOut(Request $request)
    {
        $user = $request->user();
        $now = now('Asia/Manila');
        $today = $now->toDateString();

        $log = AttendanceLog::where('user_id', $user->id)->where('date', $today)->first();
        if (! $log || ! $log->clock_in) {
            return back()->with('error', 'Time in first before timing out.');
        }
        if ($log->clock_out) {
            return back()->with('error', 'Already timed out today.');
        }
        $log->update(['clock_out' => $now->format('h:i A')]);

        return back()->with('message', 'Time out recorded at ' . $now->format('h:i A'));
    }

    public function requestLeave(Request $request)
    {
        $validated = $request->validate([
            'leave_type' => 'required|exists:hrm_leave_types,code',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500',
        ]);

        LeaveRequest::create([
            'user_id' => $request->user()->id,
            'leave_type' => $validated['leave_type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return back()->with('message', 'Leave request submitted — pending approval in Leave Management.');
    }

    private function resolveStatus(AttendanceLog $log, int $userId): string
    {
        if ($log->clock_in && $log->clock_out) {
            $date = substr((string) ($log->date instanceof \Carbon\Carbon ? $log->date->toDateString() : $log->date), 0, 10);
            $shift = EmployeeShift::where('user_id', $userId)->where('effective_date', $date)->first();
            try {
                $start = Carbon::parse($date.' '.self::shiftStartFor($shift), 'Asia/Manila');
                try {
                    $in = Carbon::createFromFormat('h:i A', (string) $log->clock_in);
                } catch (\Throwable) {
                    $in = Carbon::parse($date.' '.$log->clock_in, 'Asia/Manila');
                    return $in->gt($start) ? 'Late' : 'On-Time';
                }
                $inOnDate = Carbon::parse($date.' '.$in->format('H:i:s'), 'Asia/Manila');
                return $inOnDate->gt($start) ? 'Late' : 'On-Time';
            } catch (\Throwable) {
            }

            return 'Present';
        }

        return 'Incomplete';
    }

    private static function shiftStartFor($shift): string
    {
        $range = is_object($shift) ? ($shift->schedule_range ?? null) : null;
        $type = is_object($shift) ? ($shift->shift_type ?? null) : $shift;
        if ($range && preg_match('/(\d{1,2})(?::(\d{2}))?\s*(AM|PM)/i', explode('-', (string) $range)[0] ?? '', $m)) {
            $h = (int) $m[1]; $min = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0; $ap = strtoupper($m[3]);
            if ($ap === 'PM' && $h < 12) $h += 12;
            if ($ap === 'AM' && $h == 12) $h = 0;
            return sprintf('%02d:%02d:00', $h, $min);
        }
        $ps = \App\Models\Hrm\PayrollSetting::cached();
        return match ($type) {
            'Morning' => (string) ($ps['morning_start'] ?? '08:00:00'),
            'Afternoon' => (string) ($ps['afternoon_start'] ?? '14:00:00'),
            'Graveyard' => (string) ($ps['graveyard_start'] ?? '22:00:00'),
            default => (string) ($ps['morning_start'] ?? '08:00:00'),
        };
    }

    private function onLeave(int $userId, string $date): bool
    {
        return LeaveRequest::where('user_id', $userId)->where('status', 'approved')
            ->where('start_date', '<=', $date)->where('end_date', '>=', $date)->exists();
    }

    private static function leaveName(?string $code): string
    {
        $type = HrmLeaveType::where('code', strtolower((string) $code))->first();

        return $type?->name ?? ucwords(str_replace(['_', '-'], ' ', (string) $code));
    }

    private static function display(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'on-time', 'ontime', 'present' => 'Present',
            'late' => 'Late',
            'absent' => 'Absent',
            'on leave', 'on_leave', 'on-leave', 'leave' => 'On Leave',
            'half day', 'half_day', 'half-day', 'halfday' => 'Half Day',
            'rest day', 'rest_day', 'rest-day', 'restday', 'rest' => 'Rest Day',
            'incomplete' => 'Incomplete',
            'in progress', 'in_progress', 'working' => 'In Progress',
            default => ucwords(str_replace(['_', '-'], ' ', (string) ($status ?: 'Absent'))),
        };
    }

    private static function hoursLabel(?string $in, ?string $out): string
    {
        if (! $in || ! $out) {
            return '—';
        }
        try {
            $start = Carbon::createFromFormat('h:i A', $in);
            $end = Carbon::createFromFormat('h:i A', $out);
            if ($end->lessThan($start)) {
                $end->addDay();
            }
            $mins = (int) round($start->floatDiffInMinutes($end));
            $h = intdiv($mins, 60);
            $m = $mins % 60;

            return $m === 0 ? "{$h}h" : "{$h}h {$m}m";
        } catch (\Throwable) {
            return '—';
        }
    }
}
