<?php

namespace App\Support;

use App\Models\Core\User;
use App\Models\Hrm\Agency;
use App\Models\Hrm\Attendance;
use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\BillingStatement;
use App\Models\Hrm\ManpowerAssignment;
use App\Models\Hrm\Worker;
use Illuminate\Support\Carbon;

/**
 * Billing math shared by statement preview, issue and exports.
 *
 * The billable set is the agency's saved attendance (any approval) plus the
 * live time-clock logs (attendance_logs) for its workers — the same data the
 * HRM Worker Attendance sheet shows. A saved record always wins over a log
 * for the same worker + day, so nothing is double counted.
 *
 *   labor cost  = Σ per-day regular hours (≤ 8) × hourly rate + overtime
 *                 hours (beyond 8) × hourly rate × 1.25, using the approval
 *                 snapshot rate (or the live daily rate while unapproved)
 *   service fee = percentage of labor cost, or the fixed contract amount
 *   total       = labor cost + service fee
 */
class BillingCalc
{
    /**
     * Not-yet-billed saved attendance for an agency + period. Every saved
     * record counts (any approval state).
     */
    public static function billable(Agency $agency, string $from, string $to)
    {
        return Attendance::with(['worker:id,code,name,daily_rate', 'request:id,code,department'])
            ->whereHas('worker', fn ($q) => $q->where('agency_id', $agency->id))
            ->whereNull('billed_statement_id')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->get();
    }

    /**
     * Normalized billable rows: saved attendance first, then clock logs for
     * worker + days that have no saved record.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function billableRows(Agency $agency, string $from, string $to): array
    {
        $rows = [];
        $seen = [];

        foreach (static::billable($agency, $from, $to) as $a) {
            $row = static::recordRow($a);
            $rows[] = $row;
            $seen[$row['worker_id'] . '|' . $row['date']] = true;
        }

        foreach (static::logRows($agency, $from, $to) as $row) {
            $key = $row['worker_id'] . '|' . $row['date'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Log-derived rows for the agency's workers (worker login matched by
     * personal email, tie-broken by name — same rule as the attendance sheet).
     *
     * @return array<int,array<string,mixed>>
     */
    protected static function logRows(Agency $agency, string $from, string $to): array
    {
        $workers = Worker::with(['activeAssignments.request:id,code,department'])
            ->where('agency_id', $agency->id)
            ->whereNotNull('email')
            ->get(['id', 'code', 'name', 'email', 'daily_rate', 'agency_id']);

        if ($workers->isEmpty()) {
            return [];
        }

        $candidateUsers = User::whereNotNull('personal_email')
            ->get(['id', 'name', 'personal_email'])
            ->groupBy(fn ($u) => strtolower((string) $u->personal_email));

        $userToWorker = [];
        foreach ($workers as $w) {
            $candidates = $candidateUsers->get(strtolower((string) $w->email), collect());
            $match = $candidates->first(fn ($u) => strtolower(trim((string) $u->name)) === strtolower(trim((string) $w->name)))
                ?: ($candidates->count() === 1 ? $candidates->first() : null);
            if ($match) {
                $userToWorker[$match->id] = $w;
            }
        }
        if (empty($userToWorker)) {
            return [];
        }

        $logs = AttendanceLog::whereIn('user_id', array_keys($userToWorker))
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->get(['user_id', 'date', 'clock_in', 'clock_out', 'status']);

        $rows = [];
        $seen = [];
        foreach ($logs as $log) {
            $w = $userToWorker[$log->user_id];
            $date = substr((string) ($log->date instanceof Carbon ? $log->date->toDateString() : $log->date), 0, 10);
            $key = $w->id . '|' . $date;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $timeIn = static::toHm($log->clock_in);
            $timeOut = static::toHm($log->clock_out);
            $asg = $w->activeAssignments->first();

            $rows[] = [
                'worker_id' => $w->id,
                'worker_code' => $w->code,
                'worker_name' => $w->name,
                'request_id' => $asg?->request_id,
                'request_code' => $asg?->request?->code,
                'department' => $asg?->request?->department,
                'status' => static::logStatus($log->status),
                'time_in' => $timeIn,
                'time_out' => $timeOut,
                'hours' => (float) (AttendanceCalc::hours($timeIn, $timeOut) ?? 0),
                'overtime_hours' => 0.0,
                'rate' => (float) ($w->daily_rate ?? 0),
                'date' => $date,
                'source' => 'log',
            ];
        }

        return $rows;
    }

    /**
     * Turn clock logs that have no saved attendance row into real
     * agency_attendances records (Approved, rate snapshotted) so they can be
     * marked billed and never billed twice. Returns how many were created.
     */
    public static function persistLogAttendance(Agency $agency, string $from, string $to): int
    {
        $rows = static::logRows($agency, $from, $to);
        if (empty($rows)) {
            return 0;
        }

        $existing = Attendance::whereHas('worker', fn ($q) => $q->where('agency_id', $agency->id))
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->get(['worker_id', 'date'])
            ->mapWithKeys(fn ($a) => [
                $a->worker_id . '|' . substr((string) ($a->date instanceof Carbon ? $a->date->toDateString() : $a->date), 0, 10) => true,
            ]);

        $created = 0;
        foreach ($rows as $row) {
            if (! $row['request_id']) {
                continue; // agency_attendances.request_id is required
            }
            $key = $row['worker_id'] . '|' . $row['date'];
            if (isset($existing[$key])) {
                continue;
            }

            Attendance::create([
                'worker_id' => $row['worker_id'],
                'request_id' => $row['request_id'],
                'date' => $row['date'],
                'time_in' => $row['time_in'],
                'time_out' => $row['time_out'],
                'hours' => $row['hours'],
                'overtime_hours' => $row['overtime_hours'],
                'status' => $row['status'] === 'Holiday' ? 'Present' : $row['status'],
                'approval' => Attendance::APPROVAL_APPROVED,
                'approved_at' => now(),
                'rate_snapshot' => $row['rate'],
                'remarks' => 'Auto-recorded from the time clock when billed.',
            ]);
            $existing[$key] = true;
            $created++;
        }

        return $created;
    }

    /**
     * @return array{lines:array,labor_cost:float,service_fee:float,total:float,count:int}
     */
    public static function totals($attendances, Agency $agency): array
    {
        $rows = [];
        foreach ($attendances as $a) {
            $rows[] = static::recordRow($a);
        }

        return static::totalsFromRows($rows, $agency);
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array{lines:array,labor_cost:float,service_fee:float,total:float,count:int}
     */
    public static function totalsFromRows(array $rows, Agency $agency): array
    {
        $lines = [];
        $labor = 0.0;
        $otMultiplier = $agency->otMultiplier();

        foreach ($rows as $r) {
            $key = $r['worker_id'] . ':' . $r['request_id'];
            if (! isset($lines[$key])) {
                $lines[$key] = [
                    'worker_id' => $r['worker_id'],
                    'worker_code' => $r['worker_code'],
                    'worker_name' => $r['worker_name'],
                    'request_id' => $r['request_id'],
                    'request_code' => $r['request_code'],
                    'department' => $r['department'],
                    'days' => 0.0,
                    'hours' => 0.0,
                    'overtime_hours' => 0.0,
                    'rate' => ((float) $r['rate']) > 0 ? (float) $r['rate'] : null,
                    'labor' => 0.0,
                ];
            }
            $charge = AttendanceCalc::dayCharge($r['status'], $r['rate'], $r['overtime_hours'], (float) $r['hours'], $otMultiplier);
            $line = round($charge['total'], 2);
            $lines[$key]['days'] += AttendanceCalc::daysFactor($r['status']);
            $lines[$key]['hours'] += (float) $r['hours'];
            $lines[$key]['overtime_hours'] += $charge['ot_hours'];
            $lines[$key]['labor'] = round($lines[$key]['labor'] + $line, 2);
            $labor = round($labor + $line, 2);
        }

        $fee = $agency->service_fee_type === 'fixed'
            ? (float) ($agency->service_fee_value ?? 0)
            : round($labor * ((float) ($agency->service_fee_value ?? 0)) / 100, 2);

        return [
            'lines' => array_values($lines),
            'labor_cost' => $labor,
            'service_fee' => $fee,
            'total' => round($labor + $fee, 2),
            'count' => count($rows),
        ];
    }

    public static function preview(Agency $agency, string $from, string $to): array
    {
        $totals = static::totalsFromRows(static::billableRows($agency, $from, $to), $agency);

        // Workers still deployed to Monti with no attendance or clock log in
        // the period appear as zero lines, so HR sees every worker.
        $present = [];
        foreach ($totals['lines'] as $line) {
            $present[$line['worker_id']] = true;
        }
        $deployed = ManpowerAssignment::with(['worker:id,code,name,daily_rate', 'request:id,code,department'])
            ->whereNull('released_at')
            ->whereHas('request', fn ($q) => $q->where('agency_id', $agency->id))
            ->get();
        foreach ($deployed as $asg) {
            if (isset($present[$asg->worker_id])) {
                continue;
            }
            $present[$asg->worker_id] = true;
            $totals['lines'][] = [
                'worker_id' => $asg->worker_id,
                'worker_code' => $asg->worker?->code,
                'worker_name' => $asg->worker?->name,
                'request_id' => $asg->request_id,
                'request_code' => $asg->request?->code,
                'department' => $asg->request?->department,
                'days' => 0.0,
                'hours' => 0.0,
                'overtime_hours' => 0.0,
                'rate' => $asg->worker?->daily_rate !== null ? (float) $asg->worker->daily_rate : null,
                'labor' => 0.0,
            ];
        }

        return $totals;
    }

    public static function forStatement(BillingStatement $statement): array
    {
        $statement->loadMissing(['agency', 'attendances.worker:id,code,name', 'attendances.request:id,code,department']);

        return static::totals($statement->attendances, $statement->agency);
    }

    public static function periodLabel(string $from, string $to): string
    {
        $f = Carbon::parse($from)->format('M j, Y');
        $t = Carbon::parse($to)->format('M j, Y');

        return "{$f} – {$t}";
    }

    /** Normalize a saved Attendance model into the shared row shape. */
    protected static function recordRow(Attendance $a): array
    {
        $date = substr((string) ($a->date instanceof Carbon ? $a->date->toDateString() : $a->date), 0, 10);

        return [
            'worker_id' => $a->worker_id,
            'worker_code' => $a->worker?->code,
            'worker_name' => $a->worker?->name,
            'request_id' => $a->request_id,
            'request_code' => $a->request?->code,
            'department' => $a->request?->department,
            'status' => $a->status,
            'time_in' => $a->time_in ? substr((string) $a->time_in, 0, 5) : null,
            'time_out' => $a->time_out ? substr((string) $a->time_out, 0, 5) : null,
            'hours' => (float) ($a->hours ?? 0),
            'overtime_hours' => (float) ($a->overtime_hours ?? 0),
            'rate' => $a->rate_snapshot !== null
                ? (float) $a->rate_snapshot
                : (float) ($a->worker?->daily_rate ?? 0),
            'date' => $date,
            'source' => 'record',
        ];
    }

    /** Map a clock-log status onto the billing status vocabulary. */
    protected static function logStatus(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'late' => 'Late',
            'absent' => 'Absent',
            'half day', 'half-day', 'half_day' => 'Half day',
            'on leave', 'on_leave', 'on-leave', 'leave' => 'Leave',
            'holiday' => 'Holiday',
            default => 'Present',
        };
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
}
