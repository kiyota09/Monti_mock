<?php

namespace App\Http\Controllers\Hrm\Workforce;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\HrmDepartment;
use App\Models\Hrm\HrmShift;
use App\Models\Hrm\LeaveRequest;
use App\Traits\HasPagePermissions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * HRM attendance overview. Reads the canonical `attendance_logs`
 * written by the employee clock (Users\ClockController) — no new table.
 */
class AttendanceController extends Controller
{
    use HasPagePermissions;

    public function index(Request $request)
    {
        $date = $request->get('date', Carbon::today('Asia/Manila')->toDateString());

        // History mode: ?from=YYYY-MM-DD&to=YYYY-MM-DD returns every row
        // across the range. No range cap and no row cap — all attendance
        // within [from, to] is displayed.
        $from = $request->get('from');
        $to = $request->get('to');
        $ps = \App\Models\Hrm\PayrollSetting::cached();
        $isHistory = false;
        try {
            if ($from && $to) {
                $fromD = Carbon::parse($from)->toDateString();
                $toD = Carbon::parse($to)->toDateString();
                if ($fromD <= $toD) {
                    $from = $fromD; $to = $toD; $isHistory = true;
                }
            }
        } catch (\Throwable) {
            $isHistory = false;
        }

        $q = AttendanceLog::with(['user.hrmDepartment:id,name'])
            ->whereNotIn('user_id', function ($sub) { $sub->select('users.id')->from('users')->join('workers', 'workers.email', '=', 'users.personal_email')->whereNotNull('users.personal_email'); });
        if ($isHistory) {
            $q->whereBetween('date', [$from, $to]);
        } else {
            $q->where('date', $date);
        }
        if ($s = $request->get('search')) {
            $q->whereHas('user', fn ($w) => $w->where('name', 'like', "%{$s}%"));
        }
        if ($dept = $request->get('department')) {
            // The dropdown sends the department code (e.g. DEP-IT) while the
            // table displays the linked hrm_department name — match the value
            // against the FK relation first, then legacy string columns.
            $q->whereHas('user', function ($w) use ($dept) {
                $w->where('department', $dept)
                    ->orWhere('role', $dept)
                    ->orWhereHas('hrmDepartment', fn ($h) => $h->where('code', $dept)->orWhere('name', $dept)->orWhere('category', $dept));
            });
        }

        $q->orderBy('date', $isHistory ? 'asc' : 'desc')->orderBy('id', 'desc');
        if ($isHistory) {
            // Unlimited: every log in the range, oldest first.
            $items = $q->get();
            $total = $items->count();
            $pagination = ['current_page' => 1, 'last_page' => 1, 'per_page' => $total, 'total' => $total];
        } else {
            $perPage = (int) ($ps['daily_per_page'] ?? 15);
            $perPage = $perPage > 0 ? $perPage : 15;
            $paginated = $q->paginate($perPage)->withQueryString();
            $items = collect($paginated->items());
            $pagination = [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ];
        }
        $shiftMap = $isHistory
            ? \App\Models\Hrm\EmployeeShift::whereBetween('effective_date', [$from, $to])
                ->get(['user_id', 'effective_date', 'shift_type', 'schedule_range'])
                ->keyBy(fn ($s) => $s->user_id.'|'.substr((string) $s->effective_date, 0, 10))
            : \App\Models\Hrm\EmployeeShift::where('effective_date', $date)
                ->get(['user_id', 'shift_type', 'schedule_range'])
                ->keyBy('user_id');
        // Holiday Management link: non-working holidays in scope are never Absent.
        $holMap = \App\Models\Hrm\Holiday::nonWorkingMap(
            $isHistory ? $from : $date,
            $isHistory ? $to : $date
        );

        // Shift Management is the source of truth for the schedule start and
        // grace period. Employees may be linked directly (hrm_shift_user) or
        // through the roster shift type (Morning/Afternoon/Graveyard).
        $assignedShifts = \DB::table('hrm_shift_user as su')
            ->join('hrm_shifts as hs', 'hs.id', '=', 'su.shift_id')
            ->where('hs.is_active', true)
            ->get(['su.user_id', 'hs.name', 'hs.start_time', 'hs.grace_minutes'])
            ->keyBy('user_id');
        $hrmShifts = HrmShift::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'start_time', 'grace_minutes']);

        $records = $items->filter(fn ($l) => $l !== null)->map(function ($log) use ($shiftMap, $isHistory, $holMap, $assignedShifts, $hrmShifts) {
            $shift = $isHistory ? $shiftMap->get($log->user_id.'|'.substr((string) $log->date, 0, 10)) : $shiftMap->get($log->user_id);
            $shiftLabel = $shift ? trim(($shift->shift_type ?? '') . ($shift->schedule_range ? ' (' . $shift->schedule_range . ')' : '')) : '—';
            $dateKey = substr((string) $log->date, 0, 10);
            $config = $this->resolveShiftConfig((int) $log->user_id, $shift, $assignedShifts, $hrmShifts);
            $lateMinutes = $this->lateMinutes($log, $shift, $config);
            $status = $this->effectiveStatus($log->status, $lateMinutes);
            $remarks = $log->remarks ?? '';
            if ($status === 'Absent' && isset($holMap[$dateKey])) {
                $status = 'Holiday';
                $remarks = trim($holMap[$dateKey]['name'] . ($remarks !== '' ? ' · ' . $remarks : ''));
            }

            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'employee' => $log->user?->name ?? ('User #' . $log->user_id),
                'employee_id' => $log->user?->employee_id ?: ('EMP-' . str_pad((string) $log->user_id, 4, '0', STR_PAD_LEFT)),
                'department' => $log->user?->hrmDepartment?->name ?? $log->user?->department ?? $log->user?->role,
                'shift' => $shiftLabel ?: '—',
                'date' => $log->date,
                'time_in' => $log->clock_in ?: '—',
                'time_out' => $log->clock_out ?: '—',
                'hours' => $this->hours($log->clock_in, $log->clock_out),
                'ot' => $this->ot($log->clock_in, $log->clock_out),
                'late_minutes' => $lateMinutes,
                'status' => $status,
                'status_raw' => $log->status,
                'remarks' => $remarks,
            ];
        })->values()->all();

        $scopeQ = $isHistory
            ? AttendanceLog::whereBetween('date', [$from, $to])
                ->whereNotIn('user_id', function ($sub) { $sub->select('users.id')->from('users')->join('workers', 'workers.email', '=', 'users.personal_email')->whereNotNull('users.personal_email'); })
            : AttendanceLog::where('date', $date)
            ->whereNotIn('user_id', function ($sub) {
                $sub->select('users.id')->from('users')
                    ->join('workers', 'workers.email', '=', 'users.personal_email')
                    ->whereNotNull('users.personal_email');
            });
        $dayLogs = (clone $scopeQ)->get();
        $onLeave = $isHistory
            ? LeaveRequest::where('status', 'approved')
                ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->count()
            : LeaveRequest::where('status', 'approved')
                ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->count();

        // Per-employee present/total summary for history mode (e.g. Oct 1–15 cutoff).
        $summary = [];
        if ($isHistory) {
            $sumShiftMap = \App\Models\Hrm\EmployeeShift::whereBetween('effective_date', [$from, $to])
                ->get(['user_id', 'effective_date', 'shift_type', 'schedule_range'])
                ->keyBy(fn ($s) => $s->user_id.'|'.substr((string) $s->effective_date, 0, 10));
            $summary = $dayLogs->groupBy('user_id')->map(function ($rows) use ($sumShiftMap, $assignedShifts, $hrmShifts) {
                $first = $rows->first();
                $lateMins = 0;
                $present = 0;
                $late = 0;
                foreach ($rows as $r) {
                    $sh = $sumShiftMap->get($r->user_id.'|'.substr((string) $r->date, 0, 10));
                    $cfg = $this->resolveShiftConfig((int) $r->user_id, $sh, $assignedShifts, $hrmShifts);
                    $mins = $this->lateMinutes($r, $sh, $cfg);
                    $lateMins += $mins;
                    $st = $this->effectiveStatus($r->status, $mins);
                    if ($st === 'Present') $present++;
                    if ($st === 'Late') $late++;
                }
                return [
                    'user_id' => $first->user_id,
                    'employee' => $first->user?->name ?? ('User #'.$first->user_id),
                    'employee_id' => $first->user?->employee_id,
                    'present' => $present,
                    'late' => $late,
                    'late_minutes' => $lateMins,
                    'total' => $rows->count(),
                    'dates' => $rows->pluck('date')->unique()->sort()->values()->all(),
                ];
            })->sortByDesc('present')->values()->all();
        }

        // Recompute present/late from the grace-aware shift rules.
        $presentCount = 0;
        $lateCount = 0;
        $absentCount = 0;
        foreach ($dayLogs as $log) {
            $shift = $isHistory
                ? $shiftMap->get($log->user_id.'|'.substr((string) $log->date, 0, 10))
                : $shiftMap->get($log->user_id);
            $config = $this->resolveShiftConfig((int) $log->user_id, $shift, $assignedShifts, $hrmShifts);
            $status = $this->effectiveStatus($log->status, $this->lateMinutes($log, $shift, $config));
            if ($status === 'Present') {
                $presentCount++;
            } elseif ($status === 'Late') {
                $lateCount++;
            } elseif ($status === 'Absent' && ! isset($holMap[substr((string) $log->date, 0, 10)])) {
                $absentCount++;
            }
        }

        return Inertia::render('Dashboard/HRM_NEW/Attendance', [
            'records' => $records,
            'pagination' => $pagination,
            'metrics' => [
                ['label' => $isHistory ? 'Present (range)' : 'Present Today', 'value' => (string) $presentCount],
                ['label' => 'Late', 'value' => (string) $lateCount],
                ['label' => 'Absent', 'value' => (string) $absentCount],
                ['label' => 'On Leave', 'value' => (string) $onLeave],
            ],
            'filters' => ['date' => $date, 'from' => $isHistory ? $from : '', 'to' => $isHistory ? $to : '', 'search' => $request->get('search', ''), 'department' => $request->get('department', '')],
            'history' => $isHistory ? ['from' => $from, 'to' => $to, 'summary' => $summary] : null,
            'holidays' => collect($holMap)->map(fn ($h, $d) => ['date' => $d, 'name' => $h['name'], 'type' => $h['type']])->sortBy('date')->values()->all(),
            'departments' => HrmDepartment::whereNull('archived_at')->select('code', 'name')->get(),
            'permissions' => $this->getPagePermissionsForModule('HRM'),
        ]);
    }

    public function export(Request $request)
    {
        $date = $request->get('date', Carbon::today('Asia/Manila')->toDateString());
        $from = $request->get('from'); $to = $request->get('to');
        $isHistory = false;
        try {
            if ($from && $to) {
                $from = Carbon::parse($from)->toDateString(); $to = Carbon::parse($to)->toDateString();
                $isHistory = $from <= $to;
            }
        } catch (\Throwable) { $isHistory = false; }
        $fname = $isHistory ? "attendance-{$from}_to_{$to}.csv" : "attendance-{$date}.csv";

        return response()->streamDownload(function () use ($date, $from, $to, $isHistory) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'employee', 'employee_id', 'department', 'shift', 'time_in', 'time_out', 'hours', 'ot', 'late_minutes', 'status', 'remarks']);
            $shiftMap = $isHistory
                ? \App\Models\Hrm\EmployeeShift::whereBetween('effective_date', [$from, $to])
                    ->get(['user_id', 'effective_date', 'shift_type', 'schedule_range'])
                    ->keyBy(fn ($s) => $s->user_id.'|'.substr((string) $s->effective_date, 0, 10))
                : \App\Models\Hrm\EmployeeShift::where('effective_date', $date)
                    ->get(['user_id', 'shift_type', 'schedule_range'])->keyBy('user_id');
            $assignedShifts = \DB::table('hrm_shift_user as su')
                ->join('hrm_shifts as hs', 'hs.id', '=', 'su.shift_id')
                ->where('hs.is_active', true)
                ->get(['su.user_id', 'hs.name', 'hs.start_time', 'hs.grace_minutes'])
                ->keyBy('user_id');
            $hrmShifts = HrmShift::where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'name', 'start_time', 'grace_minutes']);
            $base = AttendanceLog::with(['user.hrmDepartment:id,name'])
            ->whereNotIn('user_id', function ($sub) { $sub->select('users.id')->from('users')->join('workers', 'workers.email', '=', 'users.personal_email')->whereNotNull('users.personal_email'); });
            if ($isHistory) $base->whereBetween('date', [$from, $to])->orderBy('date'); else $base->where('date', $date);
            $base->chunk(500, function ($rows) use ($out, $shiftMap, $isHistory, $assignedShifts, $hrmShifts) {
                foreach ($rows as $log) {
                    $shift = $isHistory ? $shiftMap->get($log->user_id.'|'.substr((string) $log->date, 0, 10)) : $shiftMap->get($log->user_id);
                    $config = $this->resolveShiftConfig((int) $log->user_id, $shift, $assignedShifts, $hrmShifts);
                    $late = $this->lateMinutes($log, $shift, $config);
                    fputcsv($out, [
                        $log->date,
                        $log->user?->name,
                        $log->user?->employee_id ?: ('EMP-' . str_pad((string) $log->user_id, 4, '0', STR_PAD_LEFT)),
                        $log->user?->hrmDepartment?->name ?? $log->user?->department ?? $log->user?->role,
                        $shift ? trim(($shift->shift_type ?? '') . ($shift->schedule_range ? ' (' . $shift->schedule_range . ')' : '')) : '',
                        $log->clock_in, $log->clock_out,
                        $this->hours($log->clock_in, $log->clock_out),
                        $this->ot($log->clock_in, $log->clock_out),
                        $late,
                        $this->effectiveStatus($log->status, $late), $log->remarks,
                    ]);
                }
            });
            fclose($out);
        }, $fname);
    }

    private function display(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'on-time', 'ontime', 'present' => 'Present',
            'late' => 'Late',
            'absent' => 'Absent',
            'on leave', 'on_leave', 'on-leave', 'leave' => 'On Leave',
            'half day', 'half_day', 'half-day', 'halfday' => 'Half Day',
            'rest day', 'rest_day', 'rest-day', 'restday', 'rest' => 'Rest Day',
            'on duty', 'on_duty', 'on-duty', 'onduty', 'working' => 'On Duty',
            'holiday' => 'Holiday',
            default => ucwords(str_replace(['_', '-'], ' ', (string) ($status ?: 'Absent'))),
        };
    }

    public const STATUSES = ['Present', 'Late', 'Absent', 'On Leave', 'Half Day', 'Rest Day', 'On Duty', 'Holiday'];

    public function update(Request $request, AttendanceLog $attendance)
    {
        $data = $request->validate([
            'status' => 'required|string|max:60',
            'remarks' => 'nullable|string|max:2000',
        ]);
        $norm = strtolower(trim((string) $data['status']));
        $map = [
            'present' => 'Present', 'on-time' => 'On-Time', 'late' => 'Late',
            'absent' => 'Absent', 'on leave' => 'On Leave', 'half day' => 'Half Day',
            'rest day' => 'Rest Day', 'on duty' => 'On Duty', 'holiday' => 'Holiday',
        ];
        $status = $map[$norm] ?? $data['status'];
        $attendance->update(['status' => $status, 'remarks' => $data['remarks'] ?? null]);

        return back()->with('message', 'Attendance updated.');
    }

    /**
     * Shift start as H:i:s. Parses the roster's schedule_range first
     * (e.g. "2PM - 11PM", "10PM - 6AM"), else shift_type defaults that
     * match the displayed roster (Morning 8AM, Afternoon 2PM, Graveyard 10PM).
     */
    private function shiftStartFor($shift): string
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

    /**
     * Resolve the Shift Management record that governs this employee's
     * schedule. Direct assignments win; otherwise match the roster shift
     * type (Morning / Afternoon / Graveyard) to a configured shift name.
     */
    private function resolveShiftConfig(int $userId, $shift, $assignedShifts, $hrmShifts)
    {
        if (isset($assignedShifts[$userId])) {
            return $assignedShifts[$userId];
        }

        $type = strtolower(trim((string) ($shift->shift_type ?? '')));
        if ($type === '') {
            return null;
        }

        foreach ($hrmShifts as $candidate) {
            $name = strtolower((string) ($candidate->name ?? ''));
            $match = in_array($type, ['graveyard', 'night'], true)
                ? (str_contains($name, 'graveyard') || str_contains($name, 'night'))
                : str_contains($name, $type);

            if ($match) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Shift start as H:i:s. Shift Management wins when configured, then
     * the roster schedule_range, then shift-type defaults.
     */
    private function shiftStartString($shift, $config = null): ?string
    {
        if ($config !== null) {
            if (empty($config->start_time)) {
                return null; // Flexible shift — no fixed start, never late.
            }
            try {
                return Carbon::parse($config->start_time)->format('H:i:s');
            } catch (\Throwable) {
                // Fall through to the roster/default parser.
            }
        }

        if (! $shift) {
            return null;
        }

        return $this->shiftStartFor($shift);
    }

    /**
     * Minutes past shift start (0 when on time, within grace, no shift,
     * no clock-in, or a flexible shift without a start time).
     */
    private function lateMinutes($log, $shift, $config = null): int
    {
        try {
            if (!$log || !$log->clock_in) return 0;
            if (!$shift && $config === null) return 0;
            $dateStr = substr((string) ($log->date ?? ''), 0, 10);
            if (!$dateStr) return 0;
            $startStr = $this->shiftStartString($shift, $config);
            if ($startStr === null) return 0;
            $shiftStart = Carbon::parse($dateStr.' '.$startStr, 'Asia/Manila');
            $clockIn = Carbon::parse($dateStr.' '.$log->clock_in, 'Asia/Manila');
            if ($clockIn->gt($shiftStart)) {
                $minutes = (int) abs($clockIn->diffInMinutes($shiftStart));
                $grace = max(0, (int) (is_object($config) ? ($config->grace_minutes ?? 15) : 15));
                return $minutes > $grace ? $minutes : 0;
            }
            return 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Grace-aware status: present logs become Late when they exceed the
     * shift grace period, and Late logs fall back to Present when inside it.
     */
    private function effectiveStatus(?string $raw, int $lateMinutes): string
    {
        $lower = strtolower(trim((string) $raw));
        if (in_array($lower, ['present', 'on-time', 'ontime', 'late'], true)) {
            return $lateMinutes > 0 ? 'Late' : 'Present';
        }

        return $this->display($raw);
    }

    private function worked(?string $in, ?string $out): ?float
    {
        if (! $in || ! $out) {
            return null;
        }
        try {
            $start = Carbon::createFromFormat('h:i A', $in);
            $end = Carbon::createFromFormat('h:i A', $out);
            if ($end->lessThan($start)) {
                $end->addDay();
            }
            return round($start->floatDiffInHours($end), 1);
        } catch (\Throwable) {
            return null;
        }
    }

    private function workedSeconds(?string $in, ?string $out): ?int
    {
        if (! $in || ! $out) {
            return null;
        }
        try {
            try {
                $start = Carbon::createFromFormat('h:i A', $in);
            } catch (\Throwable) {
                $start = Carbon::parse($in);
            }
            try {
                $end = Carbon::createFromFormat('h:i A', $out);
            } catch (\Throwable) {
                $end = Carbon::parse($out);
            }
            if ($end->lessThan($start)) {
                $end->addDay();
            }
            return (int) abs($start->diffInSeconds($end));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Duration as "8h 05m 00s" (clock times only carry minute precision, so seconds are 00). */
    private function hours(?string $in, ?string $out): string
    {
        $secs = $this->workedSeconds($in, $out);
        if ($secs === null) return '0h 00m 00s';
        $h = intdiv($secs, 3600);
        $m = intdiv($secs % 3600, 60);
        $s = $secs % 60;
        return sprintf('%dh %02dm %02ds', $h, $m, $s);
    }

    private function ot(?string $in, ?string $out): float
    {
        $h = $this->worked($in, $out);
        return $h === null ? 0 : round(max(0, $h - 8), 1);
    }
}
