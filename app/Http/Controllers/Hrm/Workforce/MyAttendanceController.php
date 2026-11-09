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

        // Workforce Shift Management stores assignments in hrm_shift_user.
        // Resolve the latest active assignment for each attendance date.
        $assignedShifts = \DB::table('hrm_shift_user as su')
            ->join('hrm_shifts as s', 's.id', '=', 'su.shift_id')
            ->where('su.user_id', $user->id)
            ->where('s.is_active', true)
            ->where(function ($query) use ($monthEnd) {
                $query->whereNull('su.effective_date')
                    ->orWhereDate('su.effective_date', '<=', $monthEnd->toDateString());
            })
            ->select('s.id', 's.code', 's.name', 's.shift_type', 's.start_time', 's.end_time', 's.working_days', 'su.effective_date')
            ->get();

        // Non-working holidays (Holiday Management): never Absent on these days.
        $holidays = \App\Models\Hrm\Holiday::nonWorkingMap($monthStart->toDateString(), $monthEnd->toDateString());

        // Employment start (deployment): days before this are not Absent.
        // Deployed worker logins carry join_date = deployment date; fall back
        // to the account creation date so pre-deployment days show '—'.
        $employmentStart = null;
        try {
            $joinDate = $user->join_date
                ? substr((string) ($user->join_date instanceof \Carbon\Carbon ? $user->join_date->toDateString() : $user->join_date), 0, 10)
                : null;
            $createdDate = $user->created_at
                ? substr((string) ($user->created_at instanceof \Carbon\Carbon ? $user->created_at->toDateString() : $user->created_at), 0, 10)
                : null;
            $employmentStart = $joinDate ?: $createdDate;
            if ($employmentStart && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $employmentStart)) {
                $employmentStart = null;
            }
        } catch (\Throwable) {
            $employmentStart = null;
        }

        // ---- Day-by-day rows (1st → today for the current month) ----
        $rows = [];
        if ($monthStart->toDateString() <= $today) {
            for ($d = clone $monthStart; $d->toDateString() <= $rangeEnd; $d->addDay()) {
                $key = $d->toDateString();
                $log = $logs->get($key);
                $leave = $leaves->first(fn ($l) => $key >= substr((string) $l->start_date, 0, 10) && $key <= substr((string) $l->end_date, 0, 10));
                $shift = $assignedShifts
                    ->filter(fn ($assignment) => ! $assignment->effective_date || substr((string) $assignment->effective_date, 0, 10) <= $key)
                    ->sortByDesc(fn ($assignment) => $assignment->effective_date ? substr((string) $assignment->effective_date, 0, 10) : '0000-00-00')
                    ->first();
                $legacyShift = $shifts->get($key);
                $isSunday = ((int) $d->format('w')) === 0;

                if ($shift) {
                    $assignedSchedule = self::assignedShiftLabel($shift);
                } elseif ($legacyShift) {
                    $assignedSchedule = trim(($legacyShift->shift_type ?? '') . ($legacyShift->schedule_range ? ' (' . $legacyShift->schedule_range . ')' : '')) ?: 'â€”';
                } else {
                    $assignedSchedule = null;
                }

                $schedule = $assignedSchedule ?? '—';

                if ($employmentStart && $key < $employmentStart) {
                    $status = '—';
                    $remark = '';
                } elseif ($log) {
                    $status = self::display($log->status);
                    $hasOpenSession = self::hasOpenSession($log);
                    if ($hasOpenSession && $key < $today) {
                        $status = 'Incomplete';
                    } elseif ($hasOpenSession && $key === $today && $log->clock_in) {
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
                    'hours' => self::hoursLabel($log?->clock_in, $log?->clock_out, self::normalizedSessions($log)),
                    'sessions' => self::sessionRows($log),
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
        $todayShift = $assignedShifts
            ->filter(fn ($assignment) => ! $assignment->effective_date || substr((string) $assignment->effective_date, 0, 10) <= $today)
            ->sortByDesc(fn ($assignment) => $assignment->effective_date ? substr((string) $assignment->effective_date, 0, 10) : '0000-00-00')
            ->first();
        $todayLegacyShift = $shifts->get($today);
        if ($todayShift) {
            // Keep the existing today-card formatter compatible with the
            // roster record returned from hrm_shift_user.
            $todayShift->shift_type = $todayShift->name ?: $todayShift->shift_type;
            $todayShift->schedule_range = self::assignedShiftTimeRange($todayShift);
        }
        $canClockIn = ! self::hasOpenSession($todayLog)
            && count(self::normalizedSessions($todayLog)) < 2
            && ! $todayLeave;
        if ($canClockIn && ! self::normalizedSessions($todayLog)) {
            $canClockIn = self::canStartSessionNow($todayShift ?: $todayLegacyShift, now('Asia/Manila'));
        }
        $todayStatus = $todayLeave ? 'On Leave'
            : ($todayLog ? (self::hasOpenSession($todayLog) ? 'In Progress' : self::display($todayLog->status)) : '—');

        return Inertia::render('Dashboard/HRM_NEW/MyAttendance', [
            'today' => [
                'date' => $today,
                'date_label' => now('Asia/Manila')->format('F j, Y'),
                'time_in' => $todayLog?->clock_in ?: '—',
                'time_out' => $todayLog?->clock_out ?: '—',
                'hours' => self::hoursLabel($todayLog?->clock_in, $todayLog?->clock_out, self::normalizedSessions($todayLog)),
                'sessions' => self::sessionRows($todayLog),
                'status' => $todayStatus,
                'leave_type' => $todayLeave ? self::leaveName($todayLeave->leave_type) : null,
                'schedule' => $todayShift
                    ? (trim(($todayShift->shift_type ?? '') . ($todayShift->schedule_range ? ' (' . $todayShift->schedule_range . ')' : '')) ?: '—')
                    : ($todayLegacyShift ? (trim(($todayLegacyShift->shift_type ?? '') . ($todayLegacyShift->schedule_range ? ' (' . $todayLegacyShift->schedule_range . ')' : '')) ?: '—') : '—'),
                'can_clock_in' => $canClockIn,
                'can_clock_out' => (bool) ($todayLog && self::hasOpenSession($todayLog) && ! $todayLeave),
            ],
            'summary' => $summary,
            'history' => array_values($rows),
            'leaveTypes' => HrmLeaveType::where('is_active', true)->orderBy('name')->select('code', 'name')->get(),
            'filters' => ['month' => $monthInput, 'date' => $request->get('date', ''), 'status' => $request->get('status', '')],
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    protected static function assignedShiftTimeRange($shift): string
    {
        $format = static function ($time): string {
            $value = substr((string) $time, 0, 5);
            return preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '';
        };
        $start = $format($shift->start_time ?? null);
        $end = $format($shift->end_time ?? null);

        return $start && $end ? $start . ' - ' . $end : '';
    }

    protected static function assignedShiftLabel($shift): string
    {
        $name = trim((string) ($shift->name ?? '')) ?: trim((string) ($shift->shift_type ?? ''));
        $range = self::assignedShiftTimeRange($shift);

        return trim($name . ($range ? ' (' . $range . ')' : '')) ?: 'â€”';
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
        if ($log && self::hasOpenSession($log)) {
            return back()->with('error', 'Already timed in. Please time out first.');
        }
        if (count(self::normalizedSessions($log)) >= 2) {
            return back()->with('error', 'You can only have two work sessions per shift.');
        }

        $shift = EmployeeShift::where('user_id', $user->id)->where('effective_date', $today)->first();
        if (! $shift) {
            $shift = \DB::table('hrm_shift_user as su')
                ->join('hrm_shifts as s', 's.id', '=', 'su.shift_id')
                ->where('su.user_id', $user->id)
                ->where('s.is_active', true)
                ->where(function ($query) use ($today) {
                    $query->whereNull('su.effective_date')
                        ->orWhereDate('su.effective_date', '<=', $today);
                })
                ->orderByDesc('su.effective_date')
                ->select('s.shift_type', 's.start_time', 's.end_time', 'su.effective_date')
                ->first();
        }
        if (! $shift) {
            $shift = EmployeeShift::firstOrCreate(
                ['user_id' => $user->id, 'effective_date' => $today],
                ['shift_type' => 'Morning', 'schedule_range' => '8AM - 5PM']
            );
        }

        // The first session must start during the assigned shift window. A
        // later session is allowed after a valid first session, so the
        // employee can clock back in after the scheduled break.
        if (! $log || ! self::normalizedSessions($log)) {
            if (! self::canStartSessionNow($shift, $now)) {
                return back()->with('error', 'Time in is only available during your assigned shift hours.');
            }
        }

        $late = false;
        try {
            $late = $now->gt(Carbon::parse($today.' '.self::shiftStartFor($shift), 'Asia/Manila'));
        } catch (\Throwable) {
        }

        $timeIn = $now->format('h:i A');
        $sessions = self::normalizedSessions($log);
        $sessions[] = ['clock_in' => $timeIn, 'clock_out' => null];
        if ($log) {
            $log->update([
                'sessions' => $sessions,
                'status' => $log->status ?: ($late ? 'Late' : 'On-Time'),
                'clock_in' => $log->clock_in ?: $timeIn,
                'clock_out' => null,
            ]);
        } else {
            AttendanceLog::create([
                'user_id' => $user->id,
                'date' => $today,
                'clock_in' => $timeIn,
                'status' => $late ? 'Late' : 'On-Time',
                'sessions' => $sessions,
            ]);
        }

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
        $sessions = self::normalizedSessions($log);
        $openIndex = null;
        for ($i = count($sessions) - 1; $i >= 0; $i--) {
            if (! empty($sessions[$i]['clock_in']) && empty($sessions[$i]['clock_out'])) {
                $openIndex = $i;
                break;
            }
        }
        if ($openIndex === null) {
            return back()->with('error', 'There is no open work session to time out.');
        }
        $sessions[$openIndex]['clock_out'] = $now->format('h:i A');
        $log->update([
            'sessions' => $sessions,
            'clock_out' => $now->format('h:i A'),
        ]);

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
        $startTime = is_object($shift) ? ($shift->start_time ?? null) : null;
        if ($startTime) {
            $parsed = self::parseShiftTime($startTime);
            if ($parsed) {
                return $parsed;
            }
        }
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

    private static function parseShiftTime($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2})(?::(\d{2}))?(?::\d{2})?\s*(AM|PM)?$/i', $value, $m)) {
            $hour = (int) $m[1];
            $minute = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            $period = isset($m[3]) ? strtoupper($m[3]) : null;
            if ($period === 'PM' && $hour < 12) $hour += 12;
            if ($period === 'AM' && $hour === 12) $hour = 0;
            if ($hour <= 23 && $minute <= 59) {
                return sprintf('%02d:%02d:00', $hour, $minute);
            }
        }
        return null;
    }

    private static function shiftEndFor($shift): string
    {
        $endTime = is_object($shift) ? ($shift->end_time ?? null) : null;
        if ($endTime) {
            $parsed = self::parseShiftTime($endTime);
            if ($parsed) {
                return $parsed;
            }
        }
        $range = is_object($shift) ? ($shift->schedule_range ?? null) : null;
        if ($range) {
            $parts = explode('-', (string) $range, 2);
            $parsed = self::parseShiftTime($parts[1] ?? '');
            if ($parsed) {
                return $parsed;
            }
        }
        $type = is_object($shift) ? ($shift->shift_type ?? null) : $shift;
        $ps = \App\Models\Hrm\PayrollSetting::cached();
        return match ($type) {
            'Morning' => (string) ($ps['morning_end'] ?? '17:00:00'),
            'Afternoon' => (string) ($ps['afternoon_end'] ?? '23:00:00'),
            'Graveyard' => (string) ($ps['graveyard_end'] ?? '07:00:00'),
            default => (string) ($ps['morning_end'] ?? '17:00:00'),
        };
    }

    private static function canStartSessionNow($shift, Carbon $now): bool
    {
        try {
            $start = Carbon::parse($now->toDateString() . ' ' . self::shiftStartFor($shift), 'Asia/Manila');
            $end = Carbon::parse($now->toDateString() . ' ' . self::shiftEndFor($shift), 'Asia/Manila');
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
            }
            $check = $now->copy();
            if ($check->lt($start)) {
                return false;
            }
            if ($check->gt($end)) {
                return false;
            }
            // Morning shifts use the morning portion of the day for their
            // first clock-in; later sessions cover the scheduled break.
            return ! ($start->hour < 12 && $check->hour >= 12);
        } catch (\Throwable) {
            return true;
        }
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

    private static function hoursLabel(?string $in, ?string $out, ?array $sessions = null): string
    {
        $mins = self::workedMinutes($in, $out, $sessions);
        if ($mins === null) {
            return '—';
        }
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return $m === 0 ? "{$h}h" : "{$h}h {$m}m";
    }

    private static function workedMinutes(?string $in, ?string $out, ?array $sessions = null): ?int
    {
        try {
            $sessions = $sessions ?: [['clock_in' => $in, 'clock_out' => $out]];
            $minutes = 0;
            foreach ($sessions as $session) {
                if (empty($session['clock_in']) || empty($session['clock_out'])) {
                    continue;
                }
                $start = Carbon::createFromFormat('h:i A', (string) $session['clock_in']);
                $end = Carbon::createFromFormat('h:i A', (string) $session['clock_out']);
                if ($end->lessThan($start)) {
                    $end->addDay();
                }
                $minutes += (int) round($start->floatDiffInMinutes($end));
            }
            return $minutes > 0 ? $minutes : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normalizedSessions(?AttendanceLog $log): array
    {
        if (! $log) {
            return [];
        }
        $sessions = is_array($log->sessions) ? $log->sessions : [];
        if (! $sessions && $log->clock_in) {
            $sessions = [['clock_in' => $log->clock_in, 'clock_out' => $log->clock_out]];
        }
        return array_values($sessions);
    }

    private static function hasOpenSession(?AttendanceLog $log): bool
    {
        foreach (self::normalizedSessions($log) as $session) {
            if (! empty($session['clock_in']) && empty($session['clock_out'])) {
                return true;
            }
        }
        return false;
    }

    private static function sessionRows(?AttendanceLog $log): array
    {
        return self::normalizedSessions($log);
    }
}
