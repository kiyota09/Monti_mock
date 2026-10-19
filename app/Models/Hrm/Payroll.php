<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * Synchronized with the 2026_02_16 migration and automated controller logic.
     */
    protected $fillable = [
        // Employee Identity
        'employee_id',
        'employee_name',
        'role',
        'period',
        'department',
        'job_title',

        // Basic Pay & Days Worked
        'base_salary',
        'monthly_salary',
        'allowance',
        'absent_days',
        'holiday_days',
        'days_worked',
        'daily_rate',
        'total_days_amt',

        // Night Differential
        'night_hours',
        'night_rate',
        'night_amt',

        // Overtime
        'overtime_hours',
        'ot_rate',
        'ot_amt',

        // Sunday / Special Holiday
        'sunday_restday_hours',
        'sun_sp_rate',
        'sun_sp_amt',
        'holiday_amt',

        // Lateness
        'late_minutes',
        'late_rate_min',
        'late_total_deduction',

        // Statutory Deductions (Automated)
        'sss_deduction',
        'philhealth_deduction',
        'pagibig_deduction',
        'tax_withheld',

        // Loans
        'sss_loan',
        'pf_loan',

        // Financial Totals and Review Status
        // Workflow: Draft -> Submitted -> Approved -> Paid (Paid is final)
        // Period-level lock: all rows Paid => locked (no regenerate/edit).
        'gross_pay',
        'net_pay',
        'employer_sss',
        'employer_philhealth',
        'employer_pagibig',
        'accrual_13th',
        'status',
        'paid_at',
        'paid_by',
        'payment_reference',
        'payment_method',
    ];

    /**
     * The attributes that should be cast to native types.
     * Ensures monetary values maintain high precision.
     */
    protected $casts = [
        'base_salary' => 'decimal:2',
        'monthly_salary' => 'decimal:2',
        'allowance' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'total_days_amt' => 'decimal:2',
        'night_rate' => 'decimal:2',
        'night_amt' => 'decimal:2',
        'ot_rate' => 'decimal:2',
        'ot_amt' => 'decimal:2',
        'sun_sp_rate' => 'decimal:2',
        'sun_sp_amt' => 'decimal:2',
        'holiday_amt' => 'decimal:2',
        'late_rate_min' => 'decimal:2',
        'late_total_deduction' => 'decimal:2',
        'sss_deduction' => 'decimal:2',
        'philhealth_deduction' => 'decimal:2',
        'pagibig_deduction' => 'decimal:2',
        'tax_withheld' => 'decimal:2',
        'sss_loan' => 'decimal:2',
        'pf_loan' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'employer_sss' => 'decimal:2',
        'employer_philhealth' => 'decimal:2',
        'employer_pagibig' => 'decimal:2',
        'accrual_13th' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the user that owns the payroll.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'employee_id', 'employee_id');
    }
}
