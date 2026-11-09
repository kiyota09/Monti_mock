<?php

namespace App\Services\Payroll;

use App\Models\Core\User;
use App\Models\Hrm\AdvanceAuditLog;
use App\Models\Hrm\AdvancePayment;
use App\Models\Hrm\AdvancePayrollDeduction;
use App\Models\Hrm\AdvanceRepayment;
use App\Models\Hrm\Payroll;
use Illuminate\Validation\ValidationException;

class AdvanceRepaymentService
{
    /** Reserve authorized installments against a new Draft payroll row. */
    public static function reserveForPayroll(Payroll $payroll, User $employee, float $netPay): float
    {
        if (! $payroll->cutoff_start || $netPay <= 0) {
            return 0.0;
        }

        $available = round($netPay, 2);
        $reservedTotal = 0.0;
        $advances = AdvancePayment::query()
            ->where('user_id', $employee->id)
            ->whereIn('status', ['Released', 'Partially Repaid'])
            ->where('payroll_deduction_authorized', true)
            ->whereNotNull('authorization_document_path')
            ->whereDate('actual_release_date', '<=', $payroll->cutoff_start)
            ->orderBy('actual_release_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($advances as $advance) {
            if (AdvancePayrollDeduction::where('advance_id', $advance->id)->where('payroll_id', $payroll->id)->exists()) {
                continue;
            }

            $paidCount = $advance->repayments()->count();
            $reserved = AdvancePayrollDeduction::where('advance_id', $advance->id)
                ->where('status', 'reserved')->get();
            $reservedCount = $reserved->count();
            $remaining = round((float) $advance->advance_amount
                - (float) $advance->total_repaid
                - (float) $reserved->sum('deduction_amount'), 2);
            if ($remaining <= 0) {
                continue;
            }

            if ($advance->repayment_method === 'one_time') {
                if ($paidCount > 0 || $reservedCount > 0) {
                    continue;
                }
                $installment = $remaining;
            } else {
                $installmentCount = max(1, (int) $advance->installments);
                if ($paidCount + $reservedCount >= $installmentCount) {
                    continue;
                }
                $installment = ($paidCount + $reservedCount === $installmentCount - 1)
                    ? $remaining
                    : min((float) $advance->installment_amount, $remaining);
            }

            // Never create a negative-net payroll or a partial installment.
            // The amount stays outstanding and can be scheduled in a later period.
            $installment = round($installment, 2);
            if ($installment <= 0 || $installment > $available) {
                continue;
            }

            AdvancePayrollDeduction::create([
                'advance_id' => $advance->id,
                'payroll_id' => $payroll->id,
                'deduction_amount' => $installment,
                'status' => 'reserved',
            ]);
            AdvanceAuditLog::create([
                'advance_id' => $advance->id,
                'actor_id' => auth()->id(),
                'action' => 'repayment_scheduled',
                'from_status' => $advance->status,
                'to_status' => $advance->status,
                'remarks' => 'Payroll deduction reserved; repayment is not recorded until payroll is paid.',
                'details' => [
                    'payroll_id' => $payroll->id,
                    'payroll_period' => $payroll->period,
                    'amount' => $installment,
                ],
                'created_at' => now(),
            ]);

            $available = round($available - $installment, 2);
            $reservedTotal = round($reservedTotal + $installment, 2);
            if ($available <= 0) {
                break;
            }
        }

        return $reservedTotal;
    }

    /** Convert reserved deductions into repayments only when payroll is paid. */
    public static function settlePaidPayroll(Payroll $payroll, string $paymentReference): float
    {
        $allocations = AdvancePayrollDeduction::query()
            ->where('payroll_id', $payroll->id)
            ->where('status', 'reserved')
            ->lockForUpdate()
            ->get();

        $reservedTotal = round((float) $allocations->sum('deduction_amount'), 2);
        if (abs($reservedTotal - (float) ($payroll->advance_deduction ?? 0)) > 0.01) {
            throw ValidationException::withMessages([
                'error' => 'Payroll advance deductions no longer match their reserved repayment records. Review this payroll before marking it paid.',
            ]);
        }

        foreach ($allocations as $allocation) {
            if (AdvanceRepayment::where('advance_id', $allocation->advance_id)->where('payroll_id', $payroll->id)->exists()) {
                throw ValidationException::withMessages(['error' => 'A repayment for this payroll already exists. Duplicate deduction was blocked.']);
            }

            $advance = AdvancePayment::whereKey($allocation->advance_id)->lockForUpdate()->firstOrFail();
            if (! in_array($advance->status, ['Released', 'Partially Repaid'], true)) {
                throw ValidationException::withMessages(['error' => 'Only released advances can be repaid through payroll.']);
            }

            $amount = round((float) $allocation->deduction_amount, 2);
            $outstanding = round((float) $advance->advance_amount - (float) $advance->total_repaid, 2);
            if ($amount <= 0 || $amount > $outstanding) {
                throw ValidationException::withMessages(['error' => 'The reserved repayment exceeds the advance outstanding balance.']);
            }

            AdvanceRepayment::create([
                'advance_id' => $advance->id,
                'payroll_id' => $payroll->id,
                'payroll_period' => (string) $payroll->period,
                'amount' => $amount,
                'repaid_at' => now(),
            ]);

            $previousStatus = $advance->status;
            $totalRepaid = round((float) $advance->total_repaid + $amount, 2);
            $balance = max(0, round((float) $advance->advance_amount - $totalRepaid, 2));
            $nextStatus = $balance <= 0.009 ? 'Fully Repaid' : 'Partially Repaid';
            $advance->update([
                'total_repaid' => $totalRepaid,
                'outstanding_balance' => $balance,
                'status' => $nextStatus,
            ]);
            $allocation->update(['status' => 'paid']);

            AdvanceAuditLog::create([
                'advance_id' => $advance->id,
                'actor_id' => auth()->id(),
                'action' => 'payroll_repayment',
                'from_status' => $previousStatus,
                'to_status' => $nextStatus,
                'remarks' => 'Repayment recorded from a paid payroll deduction.',
                'details' => [
                    'payroll_id' => $payroll->id,
                    'payroll_period' => $payroll->period,
                    'payment_reference' => $paymentReference,
                    'amount' => $amount,
                    'outstanding_balance' => $balance,
                ],
                'created_at' => now(),
            ]);
        }

        return $reservedTotal;
    }
}
