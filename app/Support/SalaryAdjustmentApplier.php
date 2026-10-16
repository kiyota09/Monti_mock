<?php

namespace App\Support;

use App\Models\Core\User;
use App\Models\Hrm\SalaryAdjustment;
use App\Models\Hrm\SalaryHistory;
use Illuminate\Support\Facades\DB;

/**
 * Applies approved salary adjustments whose effective date has arrived.
 *
 * Each application runs in its own transaction: users.basic_salary is
 * updated (the single value payroll reads), an immutable salary_history
 * row is appended, and the adjustment is marked Applied exactly once.
 */
class SalaryAdjustmentApplier
{
    /**
     * Apply all due adjustments. Returns the number applied.
     */
    public static function applyDue(?string $today = null): int
    {
        $today ??= now()->toDateString();
        $applied = 0;

        $due = SalaryAdjustment::whereIn('status', [
                SalaryAdjustment::STATUS_APPROVED,
                SalaryAdjustment::STATUS_SCHEDULED,
            ])
            ->whereDate('effective_date', '<=', $today)
            ->orderBy('effective_date')
            ->get();

        foreach ($due as $adjustment) {
            if (self::apply($adjustment)) {
                $applied++;
            }
        }

        return $applied;
    }

    /**
     * Apply a single approved/scheduled adjustment. Idempotent: already
     * applied rows are skipped so double application is impossible.
     */
    public static function apply(SalaryAdjustment $adjustment): bool
    {
        return (bool) DB::transaction(function () use ($adjustment) {
            $fresh = SalaryAdjustment::whereKey($adjustment->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->status === SalaryAdjustment::STATUS_APPLIED) {
                return false;
            }
            if (! in_array($fresh->status, [
                SalaryAdjustment::STATUS_APPROVED,
                SalaryAdjustment::STATUS_SCHEDULED,
            ], true)) {
                return false;
            }

            $employee = User::whereKey($fresh->user_id)->lockForUpdate()->first();
            if (! $employee) {
                return false;
            }

            $previous = $employee->basic_salary;
            $employee->update(['basic_salary' => $fresh->new_salary]);

            SalaryHistory::create([
                'user_id' => $employee->id,
                'previous_salary' => $previous,
                'new_salary' => $fresh->new_salary,
                'effective_date' => $fresh->effective_date,
                'reason' => $fresh->reason ?: $fresh->adjustment_type,
                'source' => 'adjustment',
                'salary_adjustment_id' => $fresh->id,
                'recorded_by' => $fresh->approved_by,
            ]);

            $fresh->update([
                'status' => SalaryAdjustment::STATUS_APPLIED,
                'applied_at' => now(),
            ]);

            return true;
        });
    }
}
