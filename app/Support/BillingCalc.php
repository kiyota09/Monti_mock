<?php

namespace App\Support;

use App\Models\Hrm\Agency;
use App\Models\Hrm\Attendance;
use App\Models\Hrm\BillingStatement;
use Illuminate\Support\Carbon;

/**
 * Billing math shared by statement preview, issue and exports.
 *
 *   labor cost  = Σ approved days/hours × each worker's rate snapshot
 *   service fee = percentage of labor cost, or the fixed contract amount
 *   total       = labor cost + service fee
 */
class BillingCalc
{
    /**
     * Approved, not-yet-billed attendance for an agency + period.
     */
    public static function billable(Agency $agency, string $from, string $to)
    {
        return Attendance::with(['worker:id,code,name,daily_rate', 'request:id,code,department'])
            ->whereHas('worker', fn ($q) => $q->where('agency_id', $agency->id))
            ->where('approval', Attendance::APPROVAL_APPROVED)
            ->whereNull('billed_statement_id')
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->get();
    }

    /**
     * @return array{lines:array,labor_cost:float,service_fee:float,total:float,count:int}
     */
    public static function totals($attendances, Agency $agency): array
    {
        $lines = [];
        $labor = 0.0;

        foreach ($attendances as $a) {
            $key = $a->worker_id . ':' . $a->request_id;
            if (! isset($lines[$key])) {
                $lines[$key] = [
                    'worker_id' => $a->worker_id,
                    'worker_code' => $a->worker?->code,
                    'worker_name' => $a->worker?->name,
                    'request_id' => $a->request_id,
                    'request_code' => $a->request?->code,
                    'department' => $a->request?->department,
                    'days' => 0.0,
                    'hours' => 0.0,
                    'overtime_hours' => 0.0,
                    'rate' => $a->rate_snapshot !== null ? (float) $a->rate_snapshot : null,
                    'labor' => 0.0,
                ];
            }
            $line = AttendanceCalc::labor($a->status, $a->rate_snapshot, $a->overtime_hours);
            $lines[$key]['days'] += AttendanceCalc::daysFactor($a->status);
            $lines[$key]['hours'] += (float) ($a->hours ?? 0);
            $lines[$key]['overtime_hours'] += (float) ($a->overtime_hours ?? 0);
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
            'count' => count($attendances),
        ];
    }

    public static function preview(Agency $agency, string $from, string $to): array
    {
        return static::totals(static::billable($agency, $from, $to), $agency);
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
}
