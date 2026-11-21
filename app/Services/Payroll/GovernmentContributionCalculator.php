<?php

namespace App\Services\Payroll;

use App\Support\PayPeriods;

/** Employee statutory contribution shares for Philippine payroll. */
class GovernmentContributionCalculator
{
    /** Calculate monthly employee shares from monthly basic salary. */
    public static function monthlyEmployeeShares(float $monthlyBasicSalary): array
    {
        $salary = max(0, $monthlyBasicSalary);
        if ($salary <= 0) {
            return ['sss' => 0.0, 'philhealth' => 0.0, 'pagibig' => 0.0];
        }

        // SSS 2025 employed-member schedule: employee share is 5% of MSC,
        // with MSC from ₱5,000 through ₱35,000 in ₱500 increments.
        $msc = max(5000, min(35000, (int) (round($salary / 500) * 500)));

        // PhilHealth employee share is half of the 5% premium, applied to
        // monthly basic salary with the published ₱10,000–₱100,000 bounds.
        $philhealthBase = max(10000, min(100000, $salary));

        // Pag-IBIG employee share: 1% through ₱1,500, then 2%, capped at
        // the current ₱10,000 Maximum Fund Salary.
        $pagibigRate = $salary <= 1500 ? 0.01 : 0.02;
        $pagibigBase = min(10000, $salary);

        return [
            'sss' => round($msc * 0.05, 2),
            'philhealth' => round($philhealthBase * 0.025, 2),
            'pagibig' => round($pagibigBase * $pagibigRate, 2),
        ];
    }

    /** Convert monthly shares to the selected payroll's per-period deduction. */
    public static function forPayrollPeriod(float $monthlyBasicSalary, string $frequency): array
    {
        $monthly = self::monthlyEmployeeShares($monthlyBasicSalary);
        $periodsPerMonth = max(1, PayPeriods::periodsPerMonth($frequency));

        return array_map(
            fn (float $amount) => round($amount / $periodsPerMonth, 2),
            $monthly
        );
    }
}
