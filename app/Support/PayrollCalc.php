<?php

namespace App\Support;

/**
 * Single source of truth for payroll math — daily-rate based.
 *
 * Field/Value table:
 *   Days worked × Daily rate = Basic total
 *   Night rate   = hourly × 1.10, Night amount   = night_hours × hourly × 1.10
 *   O.T. rate    = hourly × 1.25, O.T. amount    = ot_hours × hourly × 1.25
 *   Sun/SP rate  = hourly × 1.30, Sun/SP amount  = sun_hours × hourly × 1.30
 *   Late rate    = hourly / 60,   Late total     = late_minutes × late_rate
 *   Gross = basic + night + O.T. + Sun/SP + holiday − late
 *   Net   = Gross − (SSS + PhilHealth + Pag-IBIG + SSS loan + PF loan)
 *
 * hourly = daily / 8. Blank inputs = 0.
 *
 * Multipliers/divisors come from Payroll Settings (payroll_settings table,
 * PayrollSetting::cached()) with the classic 1.10/1.25/1.30/60/26/8 fallbacks.
 */
class PayrollCalc
{
    public static function calc(array $e, string $frequency = 'semi_monthly'): array
    {
        // Tunables come from Payroll Settings (DB) with hardcoded fallbacks,
        // so math never breaks when the settings table is empty/missing.
        // Callers may inject $e['settings'] to reuse one loaded map in loops.
        $s = $e['settings'] ?? \App\Models\Hrm\PayrollSetting::cached();
        $nightMult = (float) ($s['night_multiplier'] ?? 1.10);
        $otMult = (float) ($s['ot_multiplier'] ?? 1.25);
        $sunMult = (float) ($s['sun_multiplier'] ?? 1.30);
        $lateDiv = (float) ($s['late_minutes_divisor'] ?? 60) ?: 60;
        $dailyDiv = (float) ($s['daily_divisor'] ?? 26) ?: 26;
        $hrsDay = (float) ($s['hours_per_day'] ?? 8) ?: 8;
        $accrualMonths = (float) ($s['accrual_months'] ?? 12) ?: 12;

        // Days worked: prefer explicit daily inputs, fall back to attendance present count.
        $days = max(0, (float) ($e['days'] ?? $e['days_worked'] ?? $e['present'] ?? 0));

        // Daily rate: explicit first, else derive from monthly (DOLE divisor) for legacy rows.
        $daily = (float) ($e['daily'] ?? $e['daily_rate'] ?? $e['rate'] ?? 0);
        if ($daily <= 0) {
            $monthly = max(0, (float) ($e['monthly'] ?? $e['monthly_salary'] ?? $e['base_salary'] ?? 0));
            if ($monthly > 0) {
                $daily = $monthly / $dailyDiv;
            }
        }
        $daily = max(0, $daily);
        $hourly = $daily > 0 ? $daily / $hrsDay : 0;

        $nightH = max(0, (float) ($e['night_hours'] ?? $e['night'] ?? $e['ng'] ?? 0));
        $otH    = max(0, (float) ($e['ot_hours'] ?? $e['ot'] ?? 0));
        $sunH   = max(0, (float) ($e['sun_hours'] ?? $e['sun_sp_hours'] ?? $e['sunday_restday_hours'] ?? $e['sunday_hours'] ?? 0));
        $holAmt = max(0, (float) ($e['holiday_amt'] ?? $e['holiday'] ?? $e['hol_amt'] ?? 0));
        // Legacy: holiday given as days worked → convert to amount.
        $holDays = (float) ($e['holiday_days'] ?? $e['hol'] ?? 0);
        if ($holAmt <= 0 && $holDays > 0 && $daily > 0) {
            $holAmt = $holDays * $daily;
        }
        $lateM = max(0, (float) ($e['late_minutes'] ?? $e['late'] ?? 0));

        // Derived rates (always from daily, even when hours are blank).
        // night_rate is hourly-based (hourly × multiplier), consistent with
        // ot_rate / sun_rate. night_amt = night_hours × hourly × multiplier.
        $nightRate = $hourly * $nightMult;
        $otRate    = $hourly * $otMult;
        $sunRate   = $hourly * $sunMult;
        $lateRate  = $hourly > 0 ? $hourly / $lateDiv : 0;

        // Amounts.
        $basic    = $days * $daily;
        $nightAmt = $nightH * $hourly * $nightMult;
        $otAmt    = $otH * $hourly * $otMult;
        $sunAmt   = $sunH * $hourly * $sunMult;
        $lateTotal = $lateM * $lateRate;

        $gross = $basic + $nightAmt + $otAmt + $sunAmt + $holAmt - $lateTotal;

        // Deductions: blank = 0. Loans are the typical case; statutory can stay blank.
        $sss  = max(0, (float) ($e['sss'] ?? $e['sss_deduction'] ?? 0));
        $phil = max(0, (float) ($e['philhealth'] ?? $e['phil'] ?? $e['philhealth_deduction'] ?? 0));
        $pag  = max(0, (float) ($e['pagibig'] ?? $e['pag'] ?? $e['pagibig_deduction'] ?? 0));
        $sssLoan = max(0, (float) ($e['sss_loan'] ?? $e['sssLoan'] ?? 0));
        $pfLoan  = max(0, (float) ($e['pf_loan'] ?? $e['pfLoan'] ?? $e['pf_loan_amt'] ?? 0));
        // Legacy "other" folded into SSS loan for storage compat (single other_deductions input).
        if (isset($e['other']) || isset($e['other_deductions'])) {
            $other = max(0, (float) ($e['other'] ?? $e['other_deductions'] ?? 0));
            // Only fold in when explicit loan fields are both zero (old form posted a combined value).
            if ($sssLoan <= 0 && $pfLoan <= 0 && $other > 0) {
                $sssLoan = $other;
            }
        }

        $deductions = $sss + $phil + $pag + $sssLoan + $pfLoan;
        $net = $gross - $deductions;
        if ($net < 0) $net = 0;
        $capped = $deductions > $gross;

        $freq = \App\Support\PayPeriods::normalizeFrequency($e['frequency'] ?? $frequency ?? 'semi_monthly');
        $ppy = \App\Support\PayPeriods::periodsPerYear($freq);
        $ppm = $ppy / 12;
        $monthlyEquiv = $daily * $dailyDiv;

        return [
            // Inputs echoed back.
            'days' => $days, 'days_worked' => $days,
            'daily' => $daily, 'daily_rate' => $daily, 'hourly' => $hourly,
            'night_hours' => $nightH, 'overtime_hours' => $otH, 'ot_hours' => $otH,
            'sunday_hours' => $sunH, 'late_minutes' => $lateM,
            // Rates.
            'night_rate' => $nightRate, 'ot_rate' => $otRate,
            'sun_sp_rate' => $sunRate, 'sun_rate' => $sunRate,
            'late_rate_min' => $lateRate, 'late_rate' => $lateRate,
            // Amounts.
            'basic' => $basic, 'total_days_amt' => $basic, 'basic_total' => $basic,
            'night_amt' => $nightAmt, 'night_diff' => $nightAmt,
            'overtime' => $otAmt, 'ot_amt' => $otAmt,
            'sun_sp_amt' => $sunAmt, 'sun_amt' => $sunAmt,
            'holiday_pay' => $holAmt, 'holiday_amt' => $holAmt,
            'late' => $lateTotal, 'late_total' => $lateTotal, 'late_total_deduction' => $lateTotal,
            'gross' => $gross,
            // Deductions.
            'sss' => $sss, 'sss_deduction' => $sss,
            'philhealth' => $phil, 'phil' => $phil, 'philhealth_deduction' => $phil,
            'pagibig' => $pag, 'pag' => $pag, 'pagibig_deduction' => $pag,
            'sss_loan' => $sssLoan, 'pf_loan' => $pfLoan,
            'tax' => 0, 'tax_withheld' => 0,
            'deductions' => $deductions, 'ded' => $deductions, 'total_deductions' => $deductions,
            'net' => $net,
            'capped' => $capped,
            'frequency' => $freq,
            // Compat keys for callers expecting old shape.
            'absence' => 0, 'overtime_hours_compat' => $otH,
            'employer_sss' => 0, 'employer_philhealth' => 0, 'employer_pagibig' => 0,
            'accrual_13th' => $monthlyEquiv / $accrualMonths / max(1, $ppm), 'ec' => 0,
            'msc' => 0,
        ];
    }

    public static function remittances(iterable $payrolls): array
    {
        $wS = $eS = $wP = $wG = $wT = $a13 = 0;
        $count = 0;
        foreach ($payrolls as $p) {
            // Recompute from stored payroll row so totals equal sum of rows.
            $c = self::calc([
                'days' => $p->days_worked ?? 0,
                'daily' => $p->daily_rate ?? 0,
                'monthly' => $p->monthly_salary ?? $p->base_salary ?? 0,
                'night_hours' => $p->night_hours ?? 0,
                'ot_hours' => $p->overtime_hours ?? 0,
                'sun_hours' => $p->sunday_restday_hours ?? 0,
                'holiday_amt' => $p->holiday_amt ?? 0,
                'late_minutes' => $p->late_minutes ?? 0,
                'sss' => $p->sss_deduction ?? 0,
                'philhealth' => $p->philhealth_deduction ?? 0,
                'pagibig' => $p->pagibig_deduction ?? 0,
                'sss_loan' => $p->sss_loan ?? 0,
                'pf_loan' => $p->pf_loan ?? 0,
            ], \App\Support\PayPeriods::frequencyForLabel((string) ($p->period ?? '')));
            // Prefer stored deductions when present for exactness.
            $sss = (float) ($p->sss_deduction ?? $c['sss']);
            $phil = (float) ($p->philhealth_deduction ?? $c['philhealth']);
            $pag = (float) ($p->pagibig_deduction ?? $c['pagibig']);
            $tax = (float) ($p->tax_withheld ?? 0);
            $wS += $sss; $eS += 0;
            $wP += $phil; $wG += $pag; $wT += $tax; $a13 += $c['accrual_13th'];
            $count++;
        }
        return [
            'count' => $count,
            'sss_withheld' => $wS, 'sss_employer' => $eS,
            'philhealth_withheld' => $wP, 'philhealth_employer' => $wP,
            'pagibig_withheld' => $wG, 'pagibig_employer' => $wG,
            'tax_withheld' => $wT, 'accrual_13th' => $a13,
        ];
    }
}
